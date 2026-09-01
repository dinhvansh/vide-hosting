<?php

namespace Tests\Feature\Api;

use App\Jobs\ExecuteDeployment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RateLimitIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_user_journey_does_not_share_rate_limit_buckets(): void
    {
        Queue::fake();
        $registration = $this->postJson('/api/v1/auth/register', [
            'name' => 'Rate Limit User',
            'email' => 'rate-limit@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();
        $token = $registration->json('data.token');

        $this->withToken($token)->getJson('/api/v1/me')->assertOk();
        $application = $this->withToken($token)->postJson('/api/v1/apps', [
            'name' => 'Rate Limit App',
            'repository_url' => 'https://github.com/example/rate-limit-app',
        ])->assertCreated();

        $this->withToken($token)
            ->postJson('/api/v1/apps/'.$application->json('data.id').'/deployments')
            ->assertAccepted();

        Queue::assertPushed(ExecuteDeployment::class, 1);
    }
}
