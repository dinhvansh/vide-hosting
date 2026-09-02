<?php

namespace App\Services;

use App\Exceptions\PlatformException;
use App\Models\PaymentOrder;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BillingService
{
    public function __construct(private SubscriptionService $subscriptions, private SePayGateway $gateway) {}

    /** @return array{order: PaymentOrder, checkout: array{url: string, fields: array<string, string>}} */
    public function createPlanOrder(User $user, Plan $plan, int $months): array
    {
        $this->assertPaymentAvailable();
        if (! $plan->is_published || $plan->monthly_price_vnd < 1) {
            throw new PlatformException('PLAN_NOT_PURCHASABLE', 'This plan is not available for purchase.', 422);
        }

        $subscription = $this->subscriptions->for($user);
        $amount = ($plan->monthly_price_vnd + ($subscription->extra_app_slots * $this->appSlotMonthlyPrice())) * $months;
        $order = $this->newOrder($user, 'PLAN', $amount, $plan, $months, 1);

        return ['order' => $order, 'checkout' => $this->gateway->checkout($order)];
    }

    /** @return array{order: PaymentOrder, checkout: array{url: string, fields: array<string, string>}} */
    public function createAppSlotOrder(User $user, int $quantity): array
    {
        $this->assertPaymentAvailable();
        $subscription = $this->subscriptions->for($user);
        $months = max(1, (int) ceil(max(1, now()->diffInDays($subscription->ends_at ?? now()->addMonth(), false)) / 30));
        $order = $this->newOrder($user, 'APP_SLOT', $this->appSlotMonthlyPrice() * $quantity * $months, null, $months, $quantity);

        return ['order' => $order, 'checkout' => $this->gateway->checkout($order)];
    }

    /** @param array<string, mixed> $payload */
    public function processProviderPayload(array $payload): PaymentOrder
    {
        $invoice = (string) ($payload['order_invoice_number'] ?? $payload['invoice_number'] ?? '');
        $order = PaymentOrder::query()->where('invoice_number', $invoice)->firstOrFail();
        $approved = strtoupper((string) ($payload['order_status'] ?? '')) === 'APPROVED'
            || strtoupper((string) ($payload['transaction_status'] ?? '')) === 'APPROVED'
            || strtoupper((string) ($payload['status'] ?? '')) === 'APPROVED';

        if ($approved) {
            return $this->approve($order, $payload);
        }

        $order->update(['provider_payload' => $payload]);

        return $order->refresh();
    }

    /** @param array<string, mixed> $payload */
    public function approve(PaymentOrder $order, array $payload = []): PaymentOrder
    {
        return DB::transaction(function () use ($order, $payload): PaymentOrder {
            $lockedOrder = PaymentOrder::query()->lockForUpdate()->findOrFail($order->id);
            if ($lockedOrder->status === 'APPROVED') {
                return $lockedOrder;
            }
            if ($lockedOrder->status !== 'PENDING') {
                throw new PlatformException('PAYMENT_NOT_PENDING', 'This payment can no longer be approved.', 409);
            }

            $subscription = Subscription::query()->where('user_id', $lockedOrder->user_id)->lockForUpdate()->firstOrFail();
            if ($lockedOrder->type === 'APP_SLOT') {
                $subscription->increment('extra_app_slots', $lockedOrder->quantity);
            } else {
                $base = $subscription->ends_at && $subscription->ends_at->isFuture() ? $subscription->ends_at->copy() : now();
                $newEnd = $base->copy()->addMonthsNoOverflow((int) $lockedOrder->duration_months);
                $subscription->update([
                    'plan_id' => $lockedOrder->plan_id,
                    'status' => 'ACTIVE',
                    'starts_at' => now(),
                    'ends_at' => $newEnd,
                    'grace_ends_at' => $newEnd->copy()->addDays(3),
                    'reminded_7d_at' => null, 'reminded_3d_at' => null, 'reminded_1d_at' => null, 'expired_notified_at' => null,
                ]);
            }

            $subscription->refresh()->load('plan');
            $subscription->user->quota()->updateOrCreate([], [
                'max_apps' => $subscription->plan->max_apps + $subscription->extra_app_slots,
                'max_memory_mb_per_app' => $subscription->plan->max_memory_mb_per_app,
                'max_cpu_per_app' => $subscription->plan->max_cpu_per_app,
                'max_disk_mb_per_app' => $subscription->plan->max_disk_mb_per_app,
                'max_build_concurrency' => $subscription->plan->max_build_concurrency,
            ]);

            $lockedOrder->update([
                'status' => 'APPROVED', 'approved_at' => now(), 'provider_payload' => $payload,
                'provider_order_id' => $payload['order_id'] ?? null,
                'provider_transaction_id' => $payload['transaction_id'] ?? null,
            ]);

            return $lockedOrder->refresh();
        });
    }

    public function cancelStale(): int
    {
        return PaymentOrder::query()->where('status', 'PENDING')->where('expires_at', '<=', now())->update(['status' => 'CANCELLED']);
    }

    private function newOrder(User $user, string $type, int $amount, ?Plan $plan, ?int $months, int $quantity): PaymentOrder
    {
        if ($amount < 1) {
            throw new PlatformException('PAYMENT_PRICE_NOT_CONFIGURED', 'The price for this purchase is not configured.', 503);
        }

        return $user->paymentOrders()->create([
            'plan_id' => $plan?->id, 'invoice_number' => 'VIVE-'.now()->format('YmdHis').'-'.Str::upper(Str::random(8)),
            'type' => $type, 'duration_months' => $months, 'quantity' => $quantity, 'amount_vnd' => $amount,
            'status' => 'PENDING', 'expires_at' => now()->addMinutes((int) config('services.sepay.pending_ttl_minutes', 30)),
        ]);
    }

    private function appSlotMonthlyPrice(): int
    {
        return (int) config('services.billing.app_slot_monthly_price_vnd', 49000);
    }

    private function assertPaymentAvailable(): void
    {
        if (! $this->gateway->isConfigured()) {
            throw new PlatformException('PAYMENT_UNAVAILABLE', 'Payment is not configured yet. Please contact support.', 503);
        }
    }
}
