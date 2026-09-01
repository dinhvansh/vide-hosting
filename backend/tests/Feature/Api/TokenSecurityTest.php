<?php

namespace Tests\Feature\Api;

use App\Models\ApiToken;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuthTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TokenSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_logout_revokes_only_current_token(): void
    {
        $user = User::factory()->create();
        $first = app(AuthTokenService::class)->create($user, 'First')['plain_text_token'];
        $second = app(AuthTokenService::class)->create($user, 'Second')['plain_text_token'];

        $this->withToken($first)->postJson('/api/v1/auth/logout')->assertOk();
        $this->withToken($first)->getJson('/api/v1/apps')->assertUnauthorized();
        $this->withToken($second)->getJson('/api/v1/apps')->assertOk();
    }

    public function test_expired_token_is_rejected(): void
    {
        $user = User::factory()->create();
        $plainTextToken = app(AuthTokenService::class)->create($user, 'Expired')['plain_text_token'];
        ApiToken::first()->update(['expires_at' => now()->subMinute()]);

        $this->withToken($plainTextToken)->getJson('/api/v1/apps')->assertUnauthorized();
    }

    public function test_mcp_token_has_scoped_abilities_and_trusted_audit_actor(): void
    {
        $user = User::factory()->create();
        $browserToken = app(AuthTokenService::class)->create($user, 'Browser')['plain_text_token'];
        $created = $this->withToken($browserToken)->postJson('/api/v1/tokens/mcp', ['name' => 'Cursor'])->assertCreated();
        $mcpToken = $created->json('data.token');

        $this->withToken($mcpToken)->getJson('/api/v1/apps')->assertOk();
        $this->withToken($mcpToken)->deleteJson('/api/v1/tokens/'.$created->json('data.id'))->assertForbidden()->assertJsonPath('error.code', 'TOKEN_ABILITY_REQUIRED');

        $app = $this->withToken($mcpToken)->postJson('/api/v1/apps', ['name' => 'MCP App', 'repository_url' => 'https://github.com/example/mcp'])->assertCreated();
        $this->assertDatabaseHas('audit_logs', ['action' => 'application.created', 'resource_id' => $app->json('data.id'), 'actor_type' => 'MCP']);
    }

    public function test_spoofed_actor_header_does_not_change_audit_actor(): void
    {
        $user = User::factory()->create();
        $token = app(AuthTokenService::class)->create($user, 'Browser')['plain_text_token'];
        $app = $this->withHeader('X-Vive-Actor', 'MCP')->withToken($token)->postJson('/api/v1/apps', ['name' => 'Browser App', 'repository_url' => 'https://github.com/example/browser'])->assertCreated();

        $this->assertDatabaseHas('audit_logs', ['resource_id' => $app->json('data.id'), 'actor_type' => 'USER']);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'mcp.tool_called']);
    }

    public function test_mcp_read_is_audited_with_trusted_tool_name_and_request_id(): void
    {
        $user = User::factory()->create();
        $token = app(AuthTokenService::class)->create($user, 'MCP', 'MCP', ['*'])['plain_text_token'];

        $response = $this->withHeader('X-Vive-Actor', 'forged.tool')->withToken($token)->getJson('/api/v1/apps')->assertOk();
        $audit = AuditLog::where('action', 'mcp.tool_called')->sole();

        $this->assertSame('MCP', $audit->actor_type);
        $this->assertSame($user->id, $audit->actor_id);
        $this->assertSame('projects.list', $audit->metadata_json['tool']);
        $this->assertSame('succeeded', $audit->metadata_json['outcome']);
        $this->assertSame(200, $audit->metadata_json['status_code']);
        $this->assertSame($response->json('request_id'), $audit->request_id);
        $this->assertNotEmpty($audit->request_id);
    }

    public function test_mcp_audit_recursively_redacts_secret_inputs(): void
    {
        $user = User::factory()->create();
        $token = app(AuthTokenService::class)->create($user, 'MCP', 'MCP', ['*'])['plain_text_token'];

        $this->withToken($token)->postJson('/api/v1/apps', [
            'name' => 'Audited App',
            'repository_url' => 'https://github.com/example/audited',
            'context' => ['authorization' => 'Bearer top-secret', 'nested' => ['api_key' => 'key-secret']],
        ])->assertCreated();

        $inputs = AuditLog::where('action', 'mcp.tool_called')->sole()->metadata_json['inputs'];
        $this->assertSame('[REDACTED]', $inputs['context']['authorization']);
        $this->assertSame('[REDACTED]', $inputs['context']['nested']['api_key']);
        $this->assertStringNotContainsString('top-secret', json_encode($inputs, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('key-secret', json_encode($inputs, JSON_THROW_ON_ERROR));
    }

    public function test_failed_mcp_ownership_request_is_audited(): void
    {
        $owner = User::factory()->create();
        $actor = User::factory()->create();
        $app = Application::factory()->for($owner)->create();
        $token = app(AuthTokenService::class)->create($actor, 'MCP', 'MCP', ['*'])['plain_text_token'];

        $this->withToken($token)->getJson('/api/v1/apps/'.$app->id)->assertNotFound();
        $audit = AuditLog::where('action', 'mcp.tool_called')->sole();

        $this->assertSame($app->id, $audit->resource_id);
        $this->assertSame('projects.get', $audit->metadata_json['tool']);
        $this->assertSame('failed', $audit->metadata_json['outcome']);
        $this->assertSame(404, $audit->metadata_json['status_code']);
    }

    public function test_mcp_deployment_enforces_build_concurrency_quota_and_audits_failure(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $application = Application::factory()->for($user)->create();
        $token = app(AuthTokenService::class)->create($user, 'MCP', 'MCP', ['*'])['plain_text_token'];

        $this->withToken($token)->postJson('/api/v1/apps/'.$application->id.'/deployments')->assertAccepted();
        $this->withToken($token)->postJson('/api/v1/apps/'.$application->id.'/deployments')
            ->assertUnprocessable()
            ->assertJsonPath('error.details.quota.0', 'Concurrent build limit reached. Wait for the current deployment to finish.');

        $failedAudit = AuditLog::where('action', 'mcp.tool_called')->where('metadata_json->outcome', 'failed')->sole();
        $this->assertSame('deployments.create', $failedAudit->metadata_json['tool']);
        $this->assertSame(422, $failedAudit->metadata_json['status_code']);
    }
}
