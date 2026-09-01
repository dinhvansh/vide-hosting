<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Services\AuthTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErrorContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_not_found_uses_standard_error_contract(): void
    {
        $token = $this->token();
        $this->withToken($token)->getJson('/api/v1/apps/00000000-0000-0000-0000-000000000000')
            ->assertNotFound()->assertJsonPath('error.code', 'NOT_FOUND')->assertJsonStructure(['error' => ['code', 'message', 'details'], 'request_id']);
    }

    public function test_provider_failure_is_normalized_and_failed_creation_is_compensated(): void
    {
        $token = $this->token();
        config(['services.deployment_provider' => 'dokploy']);

        $this->withToken($token)->postJson('/api/v1/apps', ['name' => 'Provider Failure', 'repository_url' => 'https://github.com/example/failure'])
            ->assertServiceUnavailable()->assertJsonPath('error.code', 'PROVIDER_UNAVAILABLE')->assertJsonMissing(['exception']);
        $this->assertDatabaseCount('applications', 0);
    }

    private function token(): string
    {
        $user = User::factory()->create();

        return app(AuthTokenService::class)->create($user, 'Test')['plain_text_token'];
    }
}
