<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Services\AuthTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_domain_is_listed_and_cannot_be_deleted(): void
    {
        [$token, $appId] = $this->userAndApplication();
        $domains = $this->withToken($token)->getJson('/api/v1/apps/'.$appId.'/domains')->assertOk();
        $domains->assertJsonPath('data.0.type', 'PLATFORM_SUBDOMAIN')->assertJsonPath('meta.custom_domains_enabled', false);

        $this->withToken($token)->deleteJson('/api/v1/apps/'.$appId.'/domains/'.$domains->json('data.0.id'))->assertUnprocessable();
    }

    public function test_custom_domain_lifecycle_when_feature_is_enabled(): void
    {
        config(['services.custom_domains_enabled' => true]);
        [$token, $appId] = $this->userAndApplication();
        $domain = $this->withToken($token)->postJson('/api/v1/apps/'.$appId.'/domains', ['domain' => 'demo.example.com'])->assertCreated();
        $this->withToken($token)->deleteJson('/api/v1/apps/'.$appId.'/domains/'.$domain->json('data.id'))->assertOk();
        $this->assertDatabaseMissing('domains', ['domain' => 'demo.example.com']);
    }

    public function test_database_password_is_encrypted_and_shown_only_once(): void
    {
        [$token, $appId] = $this->userAndApplication();
        $created = $this->withToken($token)->postJson('/api/v1/apps/'.$appId.'/databases', ['type' => 'POSTGRESQL'])->assertCreated();
        $password = $created->json('data.password');
        $this->assertNotEmpty($password);
        $this->assertStringNotContainsString($password, (string) DB::table('databases')->value('encrypted_password'));

        $this->withToken($token)->getJson('/api/v1/apps/'.$appId.'/databases')->assertOk()->assertJsonMissing(['password' => $password])->assertJsonPath('data.0.has_password', true);
        $environment = $this->withToken($token)->getJson('/api/v1/apps/'.$appId.'/env')->assertOk();
        $this->assertEqualsCanonicalizing(
            ['DATABASE_URL', 'DB_DATABASE', 'DB_HOST', 'DB_PASSWORD', 'DB_PORT', 'DB_USERNAME', 'PORT'],
            collect($environment->json('data'))->pluck('key')->all(),
        );
        $environment->assertJsonMissing(['encrypted_value' => $password])->assertJsonMissing(['value' => $password]);
        $this->assertStringNotContainsString($password, (string) DB::table('environment_variables')->where('key', 'DATABASE_URL')->value('encrypted_value'));
    }

    public function test_usage_returns_current_values_and_enforced_limits(): void
    {
        [$token, $appId] = $this->userAndApplication();
        $this->withToken($token)->getJson('/api/v1/apps/'.$appId.'/usage')->assertOk()
            ->assertJsonPath('data.memory_mb', 0)->assertJsonPath('data.limits.memory_mb', 512)->assertJsonPath('data.limits.cpu', 0.5);
    }

    public function test_stop_and_restart_keep_application_status_in_sync(): void
    {
        [$token, $appId] = $this->userAndApplication();

        $this->withToken($token)->postJson('/api/v1/apps/'.$appId.'/stop')->assertAccepted()->assertJsonPath('data.status', 'STOPPED');
        $this->assertDatabaseHas('applications', ['id' => $appId, 'status' => 'STOPPED']);

        $this->withToken($token)->postJson('/api/v1/apps/'.$appId.'/restart')->assertAccepted()->assertJsonPath('data.status', 'RUNNING');
        $this->assertDatabaseHas('applications', ['id' => $appId, 'status' => 'RUNNING']);
    }

    /** @return array{string, string} */
    private function userAndApplication(): array
    {
        $user = User::factory()->create();
        $token = app(AuthTokenService::class)->create($user, 'Test')['plain_text_token'];
        $appId = $this->withToken($token)->postJson('/api/v1/apps', ['name' => 'Operations', 'repository_url' => 'https://github.com/example/operations'])->json('data.id');

        return [$token, $appId];
    }
}
