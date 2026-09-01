<?php

namespace Tests\Feature\Api;

use App\Exceptions\ProviderException;
use App\Jobs\ExecuteDeployment;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\Deployment;
use App\Models\Node;
use App\Models\User;
use App\Providers\FakeDeploymentProvider;
use App\Services\ApplicationLogRedactor;
use App\Services\AuthTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ApplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_one_application_and_queue_deployment(): void
    {
        Queue::fake();
        [$user, $token] = $this->userWithToken();
        $app = $this->withToken($token)->postJson('/api/v1/apps', ['name' => 'CRM Viet', 'repository_url' => 'https://github.com/example/crm', 'branch' => 'main']);
        $app->assertCreated()->assertJsonPath('data.resources.memory_mb', 512);
        $this->withToken($token)->postJson('/api/v1/apps/'.$app->json('data.id').'/deployments')->assertStatus(202)->assertJsonPath('data.status', 'QUEUED');
        Queue::assertPushed(ExecuteDeployment::class);
        $this->assertDatabaseHas('audit_logs', ['actor_id' => $user->id, 'action' => 'deployment.created']);
    }

    public function test_user_can_list_their_applications(): void
    {
        [, $token] = $this->userWithToken();
        $this->withToken($token)->getJson('/api/v1/apps')->assertOk()->assertJsonPath('meta.total', 0);
    }

    public function test_laravel_application_receives_an_encrypted_app_key(): void
    {
        [, $token] = $this->userWithToken();

        $application = $this->withToken($token)->postJson('/api/v1/apps', [
            'name' => 'Laravel app',
            'repository_url' => 'https://github.com/laravel/laravel',
            'framework' => 'laravel',
        ])->assertCreated();

        $variable = DB::table('environment_variables')->where('application_id', $application->json('data.id'))->where('key', 'APP_KEY')->first();
        $this->assertNotNull($variable);
        $this->assertTrue((bool) $variable->is_secret);
        $this->assertStringNotContainsString('base64:', $variable->encrypted_value);
    }

    public function test_dynamic_non_laravel_application_receives_the_platform_port(): void
    {
        [, $token] = $this->userWithToken();

        $application = $this->withToken($token)->postJson('/api/v1/apps', [
            'name' => 'Python app',
            'repository_url' => 'https://github.com/example/python',
            'framework' => 'python',
        ])->assertCreated();

        $this->withToken($token)->getJson('/api/v1/apps/'.$application->json('data.id').'/env')
            ->assertOk()
            ->assertJsonPath('data.0.key', 'PORT')
            ->assertJsonPath('data.0.is_secret', false);
    }

    public function test_deploy_idempotency_key_prevents_duplicate_jobs(): void
    {
        Queue::fake();
        [, $token] = $this->userWithToken();
        $appId = $this->withToken($token)->postJson('/api/v1/apps', ['name' => 'Idempotent', 'repository_url' => 'https://github.com/example/idempotent'])->json('data.id');

        $first = $this->withToken($token)->withHeader('Idempotency-Key', 'deploy-request-1')->postJson('/api/v1/apps/'.$appId.'/deployments')->assertAccepted();
        $second = $this->withToken($token)->withHeader('Idempotency-Key', 'deploy-request-1')->postJson('/api/v1/apps/'.$appId.'/deployments')->assertAccepted();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $second->assertJsonPath('meta.idempotent_replay', true);
        Queue::assertPushed(ExecuteDeployment::class, 1);
    }

    public function test_build_concurrency_quota_rejects_another_active_deployment(): void
    {
        Queue::fake();
        [, $token] = $this->userWithToken();
        $appId = $this->withToken($token)->postJson('/api/v1/apps', ['name' => 'Concurrent', 'repository_url' => 'https://github.com/example/concurrent'])->json('data.id');

        $this->withToken($token)->postJson('/api/v1/apps/'.$appId.'/deployments')->assertAccepted();
        $this->withToken($token)->postJson('/api/v1/apps/'.$appId.'/deployments')
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'QUOTA_EXCEEDED')
            ->assertJsonPath('error.details.quota.0', 'Concurrent build limit reached. Wait for the current deployment to finish.');
        Queue::assertPushed(ExecuteDeployment::class, 1);
    }

    public function test_beta_quota_rejects_second_application(): void
    {
        [, $token] = $this->userWithToken();
        $payload = ['name' => 'App', 'repository_url' => 'https://github.com/example/app'];
        $this->withToken($token)->postJson('/api/v1/apps', $payload)->assertCreated();
        $this->withToken($token)->postJson('/api/v1/apps', [...$payload, 'name' => 'Second'])->assertUnprocessable()->assertJsonPath('error.code', 'QUOTA_EXCEEDED');
    }

    public function test_secret_environment_value_is_encrypted_and_never_returned(): void
    {
        [, $token] = $this->userWithToken();
        $appId = $this->withToken($token)->postJson('/api/v1/apps', ['name' => 'Secrets', 'repository_url' => 'https://github.com/example/secrets'])->json('data.id');
        $response = $this->withToken($token)->postJson('/api/v1/apps/'.$appId.'/env', ['key' => 'OPENAI_API_KEY', 'value' => 'sk-super-secret', 'is_secret' => true]);
        $response->assertCreated()->assertJsonMissing(['value' => 'sk-super-secret'])->assertJsonPath('data.has_value', true);
        $this->assertStringNotContainsString('sk-super-secret', (string) DB::table('environment_variables')->value('encrypted_value'));
        $this->assertSame('[REDACTED]', AuditLog::where('action', 'environment.updated')->firstOrFail()->metadata_json['value']);
    }

    public function test_deployment_logs_validate_and_apply_tail_limit(): void
    {
        [$user, $token] = $this->userWithToken();
        $application = Application::factory()->for($user)->create();
        $deployment = Deployment::factory()->for($application)->create(['build_logs' => "one\ntwo\nthree\nfour"]);

        $this->withToken($token)->getJson('/api/v1/deployments/'.$deployment->id.'/logs?tail=2')
            ->assertOk()
            ->assertJsonPath('data.logs', "three\nfour");
        $this->withToken($token)->getJson('/api/v1/deployments/'.$deployment->id.'/logs?tail=0')
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
        $this->withToken($token)->getJson('/api/v1/deployments/'.$deployment->id.'/logs?tail=501')
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function test_user_can_update_application_and_the_change_is_audited(): void
    {
        [$user, $token] = $this->userWithToken();
        $application = Application::factory()->for($user)->create();

        $this->withToken($token)->patchJson('/api/v1/apps/'.$application->id, ['name' => 'Updated name', 'branch' => 'release'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated name')
            ->assertJsonPath('data.branch', 'release');

        $this->assertDatabaseHas('audit_logs', ['actor_id' => $user->id, 'action' => 'application.updated', 'resource_id' => $application->id]);
    }

    public function test_application_and_deployment_branch_validation_rejects_unsafe_names(): void
    {
        [$user, $token] = $this->userWithToken();
        $application = Application::factory()->for($user)->create();

        $this->withToken($token)->patchJson('/api/v1/apps/'.$application->id, ['branch' => 'main; deploy'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath('error.details.branch.0', 'The branch field format is invalid.');
        $this->withToken($token)->postJson('/api/v1/apps/'.$application->id.'/deployments', ['branch' => '../main deploy'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath('error.details.branch.0', 'The branch field format is invalid.');
    }

    public function test_secret_values_are_redacted_from_stored_and_returned_build_logs(): void
    {
        [$user, $token] = $this->userWithToken();
        $application = Application::factory()->for($user)->create();
        $application->environmentVariables()->create(['key' => 'API_TOKEN', 'encrypted_value' => 's3cr3t-value', 'is_secret' => true]);
        $deployment = Deployment::factory()->for($application)->create(['status' => 'QUEUED']);
        $provider = new class extends FakeDeploymentProvider
        {
            public function deploy(Node $node, Application $application, Deployment $deployment): array
            {
                return ['provider_deployment_id' => 'redaction-test', 'commit_sha' => 'abc123', 'logs' => 'token=s3cr3t-value encoded='.rawurlencode('s3cr3t-value')];
            }
        };

        (new ExecuteDeployment($deployment->id))->handle($provider, app(ApplicationLogRedactor::class));

        $storedLogs = $deployment->fresh()->build_logs;
        $this->assertStringNotContainsString('s3cr3t-value', $storedLogs);
        $this->assertStringContainsString('[REDACTED]', $storedLogs);
        $deployment->update(['build_logs' => 'legacy log s3cr3t-value']);
        $this->withToken($token)->getJson('/api/v1/deployments/'.$deployment->id.'/logs')->assertOk()->assertJsonPath('data.logs', 'legacy log [REDACTED]');
    }

    public function test_logs_are_hidden_when_a_secret_cannot_be_decrypted(): void
    {
        $application = Application::factory()->create();
        $variable = $application->environmentVariables()->create(['key' => 'BROKEN_SECRET', 'encrypted_value' => 'valid-before-corruption', 'is_secret' => true]);
        DB::table('environment_variables')->where('id', $variable->id)->update(['encrypted_value' => 'not-a-valid-encrypted-payload']);

        $logs = app(ApplicationLogRedactor::class)->redact($application, 'provider log that must fail closed');

        $this->assertSame('[VIVE_LOGS_HIDDEN: SECRET_DECRYPTION_FAILED]', $logs);
    }

    public function test_failed_deployment_logs_are_stored_with_secrets_redacted(): void
    {
        $application = Application::factory()->create();
        $application->environmentVariables()->create(['key' => 'API_TOKEN', 'encrypted_value' => 'failure-secret', 'is_secret' => true]);
        $deployment = Deployment::factory()->for($application)->create(['status' => 'QUEUED']);
        $provider = new class extends FakeDeploymentProvider
        {
            public function deploy(Node $node, Application $application, Deployment $deployment): array
            {
                throw new ProviderException('DEPLOY_FAILED', 'Provider build failed.', 422, [
                    'provider_deployment_id' => 'failed-deployment',
                    'logs' => 'railpack error token=failure-secret',
                ]);
            }
        };

        (new ExecuteDeployment($deployment->id))->handle($provider, app(ApplicationLogRedactor::class));

        $failedDeployment = $deployment->fresh();
        $this->assertSame('FAILED', $failedDeployment->status);
        $this->assertSame('DEPLOY_FAILED', $failedDeployment->error_code);
        $this->assertSame('railpack error token=[REDACTED]', $failedDeployment->build_logs);
        $this->assertSame('FAILED', $application->fresh()->status);
    }

    /** @return array{User, string} */
    private function userWithToken(): array
    {
        $user = User::factory()->create();
        $token = app(AuthTokenService::class)->create($user, 'Test')['plain_text_token'];

        return [$user, $token];
    }
}
