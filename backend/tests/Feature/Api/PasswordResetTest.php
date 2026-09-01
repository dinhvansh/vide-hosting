<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Services\AuthTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_is_non_enumerating_and_queues_mail(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'reset@example.com']);

        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertAccepted();
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'missing@example.com'])->assertAccepted();

        Notification::assertSentTo($user, ResetPasswordNotification::class, fn (ResetPasswordNotification $notification): bool => $notification->connection === 'redis-notifications' && $notification->queue === 'notifications');
    }

    public function test_password_can_be_reset_and_existing_tokens_are_revoked(): void
    {
        $user = User::factory()->create(['email' => 'reset@example.com', 'password' => 'old-password']);
        $accessToken = app(AuthTokenService::class)->create($user, 'Existing browser')['access_token'];
        $resetToken = Password::broker()->createToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => $resetToken,
            'email' => $user->email,
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ])->assertOk()->assertJsonPath('data.reset', true);

        $this->assertTrue(Hash::check('new-password123', $user->fresh()->password));
        $this->assertNotNull($accessToken->fresh()->revoked_at);
    }

    public function test_reset_mail_links_to_frontend_with_token_and_email(): void
    {
        config(['services.frontend_url' => 'https://vive.example.com']);
        $user = User::factory()->create(['email' => 'reset-link@example.com']);

        $mail = (new ResetPasswordNotification('known-reset-token'))->toMail($user);
        parse_str((string) parse_url($mail->actionUrl, PHP_URL_QUERY), $query);

        $this->assertStringStartsWith('https://vive.example.com/reset-password?', $mail->actionUrl);
        $this->assertSame('known-reset-token', $query['token'] ?? null);
        $this->assertSame('reset-link@example.com', $query['email'] ?? null);
    }
}
