<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Notifications\SubscriptionReminderNotification;
use App\Services\SubscriptionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProcessSubscriptions extends Command
{
    protected $signature = 'subscriptions:process';

    protected $description = 'Refresh subscription states and queue expiration reminders';

    public function handle(SubscriptionService $subscriptions): int
    {
        $processed = 0;
        Subscription::query()->whereNotNull('ends_at')->with(['user', 'plan'])->orderBy('ends_at')->chunkById(100, function ($items) use ($subscriptions, &$processed): void {
            foreach ($items as $item) {
                $notificationEvent = DB::transaction(function () use ($item, $subscriptions): ?string {
                    $subscription = Subscription::query()->lockForUpdate()->findOrFail($item->id);
                    $subscriptions->refreshStatus($subscription);
                    $subscription->refresh();

                    if ($subscription->ends_at->isPast()) {
                        if ($subscription->expired_notified_at === null) {
                            $subscription->update(['expired_notified_at' => now()]);

                            return 'expired';
                        }

                        return null;
                    }

                    $days = now()->diffInDays($subscription->ends_at, false);
                    foreach ([[1, 'reminded_1d_at'], [3, 'reminded_3d_at'], [7, 'reminded_7d_at']] as [$threshold, $column]) {
                        if ($days <= $threshold && $subscription->{$column} === null) {
                            $updates = [$column => now()];
                            if ($threshold <= 3 && $subscription->reminded_7d_at === null) {
                                $updates['reminded_7d_at'] = now();
                            }
                            if ($threshold <= 1 && $subscription->reminded_3d_at === null) {
                                $updates['reminded_3d_at'] = now();
                            }
                            $subscription->update($updates);

                            return (string) $threshold;
                        }
                    }

                    return null;
                }, 3);

                if ($notificationEvent !== null) {
                    $item->user->notify((new SubscriptionReminderNotification($item->fresh('plan'), $notificationEvent))->onConnection('redis-notifications')->onQueue('notifications'));
                    $processed++;
                }
            }
        });

        $this->info("Queued {$processed} subscription notification(s).");

        return self::SUCCESS;
    }
}
