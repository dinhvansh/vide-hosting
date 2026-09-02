<?php

namespace Tests\Feature;

use App\Models\PaymentOrder;
use App\Models\Plan;
use App\Models\User;
use App\Services\AuthTokenService;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.sepay', [
            'merchant_id' => 'merchant-test', 'secret_key' => 'secret-test', 'ipn_secret' => 'ipn-test',
            'api_url' => 'https://example.test', 'checkout_url' => 'https://pay.example.test', 'pending_ttl_minutes' => 30,
        ]);
    }

    public function test_user_can_create_plan_checkout_without_controlling_price(): void
    {
        [$user, $token] = $this->userAndToken();
        $plan = Plan::query()->where('code', 'PRO')->firstOrFail();

        $this->withToken($token)->postJson('/api/v1/billing/orders', [
            'type' => 'PLAN', 'plan_id' => $plan->id, 'duration_months' => 3, 'amount_vnd' => 1,
        ])->assertCreated()->assertJsonPath('data.order.amount_vnd', 447000)->assertJsonPath('data.checkout.fields.order_amount', '447000');

        $this->assertDatabaseHas('payment_orders', ['user_id' => $user->id, 'amount_vnd' => 447000, 'status' => 'PENDING']);
    }

    public function test_forged_ipn_is_rejected(): void
    {
        $order = PaymentOrder::factory()->create();

        $this->withHeader('x-secret-key', 'wrong')->postJson('/api/v1/payments/ipn', [
            'order_invoice_number' => $order->invoice_number, 'order_status' => 'APPROVED',
        ])->assertUnauthorized()->assertJsonPath('error.code', 'INVALID_PAYMENT_SIGNATURE');

        $this->assertDatabaseHas('payment_orders', ['id' => $order->id, 'status' => 'PENDING']);
    }

    public function test_approved_ipn_adds_slot_exactly_once(): void
    {
        [$user] = $this->userAndToken();
        $subscription = app(SubscriptionService::class)->for($user);
        $order = PaymentOrder::factory()->for($user)->create(['quantity' => 2]);
        $payload = ['order_invoice_number' => $order->invoice_number, 'order_status' => 'APPROVED', 'transaction_id' => 'tx-1'];

        $this->withHeader('x-secret-key', 'ipn-test')->postJson('/api/v1/payments/ipn', $payload)->assertOk();
        $this->withHeader('x-secret-key', 'ipn-test')->postJson('/api/v1/payments/ipn', $payload)->assertOk();

        $this->assertSame(2, $subscription->refresh()->extra_app_slots);
        $this->assertSame(3, $user->quota()->firstOrFail()->max_apps);
    }

    public function test_user_cannot_view_another_users_order(): void
    {
        [, $token] = $this->userAndToken();
        $order = PaymentOrder::factory()->create();

        $this->withToken($token)->getJson('/api/v1/billing/orders/'.$order->id)->assertNotFound();
    }

    public function test_stale_pending_orders_are_cancelled(): void
    {
        $order = PaymentOrder::factory()->create(['expires_at' => now()->subMinute()]);

        $this->artisan('payments:cancel-stale')->assertSuccessful();

        $this->assertSame('CANCELLED', $order->refresh()->status);
    }

    public function test_missing_gateway_configuration_does_not_create_order(): void
    {
        [, $token] = $this->userAndToken();
        config()->set('services.sepay.merchant_id', null);

        $this->withToken($token)->postJson('/api/v1/billing/orders', ['type' => 'APP_SLOT', 'quantity' => 1])
            ->assertStatus(503)->assertJsonPath('error.code', 'PAYMENT_UNAVAILABLE');
        $this->assertDatabaseCount('payment_orders', 0);
    }

    /** @return array{User, string} */
    private function userAndToken(): array
    {
        $user = User::factory()->create(['status' => 'ACTIVE']);
        $token = app(AuthTokenService::class)->create($user, 'Billing test')['plain_text_token'];

        return [$user, $token];
    }
}
