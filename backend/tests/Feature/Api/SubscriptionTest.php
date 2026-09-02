<?php

namespace Tests\Feature\Api;

use App\Models\Application;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\SubscriptionReminderNotification;
use App\Services\AuthTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_assign_a_monthly_plan_and_its_quota(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN', 'status' => 'ACTIVE']);
        $user = User::factory()->create();
        $token = app(AuthTokenService::class)->create($admin, 'Test')['plain_text_token'];
        $plan = Plan::query()->where('code', 'STARTER')->firstOrFail();
        $plan->update(['max_apps' => 3, 'max_memory_mb_per_app' => 1024]);

        $this->withToken($token)->patchJson('/api/v1/admin/users/'.$user->id.'/subscription', [
            'plan_id' => $plan->id,
            'status' => 'ACTIVE',
            'duration_months' => 3,
        ])->assertOk()
            ->assertJsonPath('data.subscription.plan.code', 'STARTER')
            ->assertJsonPath('data.subscription.status', 'ACTIVE');

        $this->assertDatabaseHas('quotas', ['user_id' => $user->id, 'max_apps' => 3, 'max_memory_mb_per_app' => 1024]);
    }

    public function test_expired_subscription_blocks_new_app_but_preserves_existing_app(): void
    {
        $user = User::factory()->create(['status' => 'ACTIVE']);
        $token = app(AuthTokenService::class)->create($user, 'Test')['plain_text_token'];
        $plan = Plan::query()->where('code', 'FREE')->firstOrFail();
        Subscription::create([
            'user_id' => $user->id, 'plan_id' => $plan->id, 'status' => 'ACTIVE', 'billing_cycle' => 'MONTHLY',
            'starts_at' => now()->subMonth(), 'ends_at' => now()->subDays(4), 'grace_ends_at' => now()->subDay(),
        ]);
        $application = Application::factory()->for($user)->create();

        $this->withToken($token)->postJson('/api/v1/apps', [
            'name' => 'Blocked app', 'repository_url' => 'https://github.com/example/repo',
        ])->assertForbidden()->assertJsonPath('error.code', 'SUBSCRIPTION_EXPIRED');

        $this->assertDatabaseHas('applications', ['id' => $application->id]);
    }

    public function test_past_due_subscription_keeps_existing_app_and_allows_grace_period(): void
    {
        $user = User::factory()->create(['status' => 'ACTIVE']);
        $token = app(AuthTokenService::class)->create($user, 'Test')['plain_text_token'];
        $plan = Plan::query()->where('code', 'FREE')->firstOrFail();
        Subscription::create([
            'user_id' => $user->id, 'plan_id' => $plan->id, 'status' => 'ACTIVE', 'billing_cycle' => 'MONTHLY',
            'starts_at' => now()->subMonth(), 'ends_at' => now()->subDay(), 'grace_ends_at' => now()->addDays(2),
        ]);

        $this->withToken($token)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.subscription.status', 'PAST_DUE');
    }

    public function test_expiration_reminder_is_queued_only_once(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $plan = Plan::query()->where('code', 'FREE')->firstOrFail();
        Subscription::create([
            'user_id' => $user->id, 'plan_id' => $plan->id, 'status' => 'ACTIVE', 'billing_cycle' => 'MONTHLY',
            'starts_at' => now()->subMonth(), 'ends_at' => now()->addHours(23), 'grace_ends_at' => now()->addDays(4),
        ]);

        $this->artisan('subscriptions:process')->assertSuccessful();
        $this->artisan('subscriptions:process')->assertSuccessful();

        Notification::assertSentToTimes($user, SubscriptionReminderNotification::class, 1);
        $this->assertDatabaseHas('subscriptions', ['user_id' => $user->id]);
    }
}
