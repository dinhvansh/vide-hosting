<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_and_resend_queue_verification_mail(): void
    {
        Notification::fake();
        $registered = $this->postJson('/api/v1/auth/register', [
            'name' => 'Verify User',
            'email' => 'verify@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();
        $user = User::where('email', 'verify@example.com')->firstOrFail();

        Notification::assertSentTo($user, VerifyEmailNotification::class, fn (VerifyEmailNotification $notification): bool => $notification->connection === 'redis-notifications' && $notification->queue === 'notifications');
        $this->withToken($registered->json('data.token'))->postJson('/api/v1/auth/email/verification-notification')->assertAccepted();
        Notification::assertSentToTimes($user, VerifyEmailNotification::class, 2);
    }

    public function test_signed_link_verifies_email(): void
    {
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(10), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $this->getJson($url)->assertOk()->assertJsonPath('data.verified', true);

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_verification_mail_links_to_frontend_with_signed_api_url(): void
    {
        config(['services.frontend_url' => 'https://vive.example.com']);
        $user = User::factory()->unverified()->create();

        $mail = (new VerifyEmailNotification)->toMail($user);
        parse_str((string) parse_url($mail->actionUrl, PHP_URL_QUERY), $query);

        $this->assertStringStartsWith('https://vive.example.com/verify-email?', $mail->actionUrl);
        $this->assertIsString($query['url'] ?? null);
        $this->getJson($query['url'])->assertOk()->assertJsonPath('data.verified', true);
    }
}
