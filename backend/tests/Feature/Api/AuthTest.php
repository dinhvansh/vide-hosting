<?php

namespace Tests\Feature\Api;

use App\Models\ApiToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_access_profile(): void
    {
        $registered = $this->postJson('/api/v1/auth/register', ['name' => 'Lan Nguyen', 'email' => 'lan@example.com', 'password' => 'password123', 'password_confirmation' => 'password123']);
        $registered->assertCreated()->assertJsonPath('data.user.status', 'BETA')->assertJsonStructure(['data' => ['token'], 'request_id']);
        $this->withToken($registered->json('data.token'))->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.email', 'lan@example.com');
    }

    public function test_api_tokens_are_hashed_at_rest(): void
    {
        $response = $this->postJson('/api/v1/auth/register', ['name' => 'An', 'email' => 'an@example.com', 'password' => 'password123', 'password_confirmation' => 'password123']);
        $token = $response->json('data.token');
        $this->assertNotSame($token, ApiToken::first()->getRawOriginal('token_hash'));
        $this->assertSame(hash('sha256', $token), ApiToken::first()->getRawOriginal('token_hash'));
    }

    public function test_long_browser_user_agent_is_safely_stored_as_token_name(): void
    {
        $response = $this->withHeader('User-Agent', str_repeat('Modern Browser Agent ', 20))->postJson('/api/v1/auth/register', [
            'name' => 'Browser User',
            'email' => 'browser@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated();
        $this->assertLessThanOrEqual(100, mb_strlen(ApiToken::firstOrFail()->name));
    }
}
