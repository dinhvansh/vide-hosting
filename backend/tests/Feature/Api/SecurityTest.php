<?php

namespace Tests\Feature\Api;

use App\Models\Application;
use App\Models\Node;
use App\Models\User;
use App\Services\AuthTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_access_another_users_application(): void
    {
        $owner = User::factory()->create();
        $app = $this->applicationFor($owner);
        $otherUser = User::factory()->create();
        $token = app(AuthTokenService::class)->create($otherUser, 'Test')['plain_text_token'];
        $this->withToken($token)->getJson('/api/v1/apps/'.$app->id)->assertNotFound();
    }

    public function test_suspended_user_cannot_deploy(): void
    {
        $user = User::factory()->create(['status' => 'SUSPENDED']);
        $token = app(AuthTokenService::class)->create($user, 'Test')['plain_text_token'];
        $app = $this->applicationFor($user);
        $this->withToken($token)->postJson('/api/v1/apps/'.$app->id.'/deployments')->assertForbidden()->assertJsonPath('error.code', 'ACCOUNT_SUSPENDED');
    }

    /** @param  array<string, mixed>  $payload */
    #[DataProvider('suspendedOperationalActions')]
    public function test_operational_action_returns_403_when_user_is_suspended(string $method, string $path, array $payload): void
    {
        $user = User::factory()->create(['status' => 'SUSPENDED']);
        $token = app(AuthTokenService::class)->create($user, 'Test')['plain_text_token'];
        $app = $this->applicationFor($user);

        $response = $this->withToken($token)->json($method, str_replace('{app}', $app->id, $path), $payload);

        $response->assertForbidden()->assertJsonPath('error.code', 'ACCOUNT_SUSPENDED');
        $this->assertDatabaseHas('applications', ['id' => $app->id, 'status' => 'CREATED']);
        $this->assertDatabaseCount('environment_variables', 0);
        $this->assertDatabaseCount('databases', 0);
        $this->assertDatabaseCount('domains', 0);
    }

    /** @return array<string, array{string, string, array<string, mixed>}> */
    public static function suspendedOperationalActions(): array
    {
        return [
            'create application' => ['POST', '/api/v1/apps', ['name' => 'Blocked', 'repository_url' => 'https://github.com/example/blocked']],
            'update application' => ['PATCH', '/api/v1/apps/{app}', ['name' => 'Blocked']],
            'delete application' => ['DELETE', '/api/v1/apps/{app}', []],
            'restart application' => ['POST', '/api/v1/apps/{app}/restart', []],
            'stop application' => ['POST', '/api/v1/apps/{app}/stop', []],
            'create environment variable' => ['POST', '/api/v1/apps/{app}/env', ['key' => 'BLOCKED', 'value' => 'secret', 'is_secret' => true]],
            'create domain' => ['POST', '/api/v1/apps/{app}/domains', ['domain' => 'blocked.example.com']],
            'create database' => ['POST', '/api/v1/apps/{app}/databases', ['type' => 'POSTGRESQL']],
        ];
    }

    private function applicationFor(User $user): Application
    {
        return Application::create(['user_id' => $user->id, 'node_id' => Node::where('code', 'HOME-01')->firstOrFail()->id, 'name' => 'Private', 'slug' => 'private-'.Str::random(5), 'repository_url' => 'https://github.com/example/private', 'branch' => 'main', 'framework' => 'auto', 'status' => 'CREATED', 'provider' => 'fake', 'cpu_limit' => .5, 'memory_limit_mb' => 512, 'disk_limit_mb' => 2048]);
    }
}
