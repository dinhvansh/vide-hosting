<?php

namespace App\Services;

use App\Exceptions\PlatformException;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;

class SubscriptionService
{
    public function for(User $user): Subscription
    {
        $subscription = $user->subscription()->first();
        if ($subscription) {
            return $this->refreshStatus($subscription);
        }

        $plan = Plan::query()->where('is_default', true)->firstOrFail();

        return $user->subscription()->create([
            'plan_id' => $plan->id,
            'status' => 'TRIALING',
            'billing_cycle' => 'MONTHLY',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'grace_ends_at' => now()->addMonth()->addDays(3),
        ]);
    }

    public function assertCanProvision(User $user): Subscription
    {
        if ($user->isAdmin()) {
            return $this->for($user);
        }

        $subscription = $this->for($user);
        if (! in_array($subscription->status, ['TRIALING', 'ACTIVE', 'PAST_DUE'], true)) {
            throw new PlatformException('SUBSCRIPTION_EXPIRED', 'Your plan has expired. Renew the plan or contact support to continue.', 403, [
                'status' => $subscription->status,
                'ended_at' => $subscription->ends_at?->toIso8601String(),
            ]);
        }

        return $subscription;
    }

    public function refreshStatus(Subscription $subscription): Subscription
    {
        if (in_array($subscription->status, ['CANCELED', 'EXPIRED'], true) || $subscription->ends_at === null) {
            return $subscription;
        }

        $status = $subscription->status;
        if ($subscription->grace_ends_at?->isPast()) {
            $status = 'EXPIRED';
        } elseif ($subscription->ends_at->isPast()) {
            $status = 'PAST_DUE';
        }

        if ($status !== $subscription->status) {
            $subscription->update(['status' => $status]);
        }

        return $subscription->refresh();
    }
}
