<?php

namespace App\Services;

use App\Models\Quota;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class QuotaService
{
    public function __construct(private SubscriptionService $subscriptions) {}

    public function for(User $user): Quota
    {
        return $user->quota()->firstOrCreate([], ['max_apps' => 1, 'max_memory_mb_per_app' => 512, 'max_cpu_per_app' => 0.5, 'max_disk_mb_per_app' => 2048, 'max_build_concurrency' => 1]);
    }

    public function assertCanCreateApplication(User $user): Quota
    {
        $this->subscriptions->assertCanProvision($user);
        $quota = $this->for($user);
        if ($user->applications()->count() >= $quota->max_apps) {
            throw ValidationException::withMessages(['quota' => ['Application limit reached.']]);
        }

        return $quota;
    }

    public function assertCanCreateDeployment(User $user): Quota
    {
        $this->subscriptions->assertCanProvision($user);
        $quota = $this->for($user);
        $activeDeployments = $user->applications()->whereHas(
            'deployments',
            fn ($query) => $query->whereIn('status', ['QUEUED', 'BUILDING', 'DEPLOYING']),
        )->withCount([
            'deployments as active_deployments_count' => fn ($query) => $query->whereIn('status', ['QUEUED', 'BUILDING', 'DEPLOYING']),
        ])->get()->sum('active_deployments_count');
        if ($activeDeployments >= $quota->max_build_concurrency) {
            throw ValidationException::withMessages(['quota' => ['Concurrent build limit reached. Wait for the current deployment to finish.']]);
        }

        return $quota;
    }
}
