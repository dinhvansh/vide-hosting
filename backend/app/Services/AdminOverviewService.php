<?php

namespace App\Services;

use App\Contracts\DeploymentProvider;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\Deployment;
use App\Models\Node;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Throwable;

class AdminOverviewService
{
    public function __construct(private DeploymentProvider $provider) {}

    /** @return array<string, mixed> */
    public function get(): array
    {
        $provider = Cache::remember('admin-overview:provider-health', now()->addSeconds(15), fn (): array => $this->provider->health());
        $primaryNode = Node::query()->orderByRaw('CASE WHEN code = ? THEN 0 ELSE 1 END', [config('nodes.default.code')])->first();
        $host = $provider['connected'] && $primaryNode
            ? Cache::remember('admin-overview:host-metrics:'.$primaryNode->id, now()->addSeconds(15), fn (): array => $this->provider->hostMetrics($primaryNode))
            : $this->unavailableHostMetrics();

        return [
            'users' => User::count(),
            'apps' => Application::count(),
            'running' => Application::where('status', 'RUNNING')->count(),
            'failed' => Application::where('status', 'FAILED')->count(),
            'queued' => Deployment::where('status', 'QUEUED')->count(),
            'provider' => $provider,
            'host' => $host,
            'recent_failures' => $this->recentFailures(),
            'top_consumers' => $this->topConsumers($provider['connected']),
            'product_metrics' => $this->productMetrics(),
        ];
    }

    /** @return array<string, int|float> */
    private function productMetrics(): array
    {
        $deployments = Deployment::query()
            ->whereIn('status', ['RUNNING', 'FAILED', 'CANCELLED'])
            ->orderBy('created_at')
            ->get(['application_id', 'status', 'created_at', 'finished_at']);
        $byApplication = $deployments->groupBy('application_id');
        $deployedApplications = $byApplication->count();
        $successfulDeployments = $deployments->where('status', 'RUNNING')->count();
        $timeToLive = $byApplication->map(function ($applicationDeployments): ?int {
            $firstLive = $applicationDeployments->first(fn (Deployment $deployment): bool => $deployment->status === 'RUNNING' && $deployment->finished_at !== null);

            return $firstLive ? (int) $firstLive->created_at->diffInSeconds($firstLive->finished_at) : null;
        })->filter(fn (?int $duration): bool => $duration !== null)->sort()->values();

        return [
            'registrations' => User::count(),
            'verified_users' => User::whereNotNull('email_verified_at')->count(),
            'application_creations' => Application::withTrashed()->count(),
            'successful_first_deployments' => $byApplication->filter(fn ($applicationDeployments): bool => $applicationDeployments->first()?->status === 'RUNNING')->count(),
            'deployment_success_rate_percent' => $deployments->isEmpty() ? 0.0 : round($successfulDeployments / $deployments->count() * 100, 1),
            'median_time_to_first_live_seconds' => $this->median($timeToLive->all()),
            'repeat_deployment_rate_percent' => $deployedApplications === 0 ? 0.0 : round($byApplication->filter(fn ($applicationDeployments): bool => $applicationDeployments->count() > 1)->count() / $deployedApplications * 100, 1),
            'active_apps_after_7_days' => Application::where('status', 'RUNNING')->where('created_at', '<=', now()->subDays(7))->count(),
            'restart_actions' => AuditLog::whereIn('action', ['application.restarted', 'admin.application_restarted'])->count(),
        ];
    }

    /** @param array<int, int> $values */
    private function median(array $values): int
    {
        if ($values === []) {
            return 0;
        }

        $middle = intdiv(count($values), 2);

        return count($values) % 2 === 1
            ? $values[$middle]
            : (int) round(($values[$middle - 1] + $values[$middle]) / 2);
    }

    /** @return array<int, array<string, mixed>> */
    private function recentFailures(): array
    {
        return Deployment::with('application.user')->whereHas('application')->where('status', 'FAILED')->latest('finished_at')->limit(10)->get()
            ->map(fn (Deployment $deployment): array => [
                'id' => $deployment->id,
                'status' => $deployment->status,
                'error_code' => $deployment->error_code,
                'error_message' => $deployment->error_message,
                'finished_at' => $deployment->finished_at ?? $deployment->updated_at,
                'application' => [
                    'id' => $deployment->application->id,
                    'name' => $deployment->application->name,
                    'owner' => $deployment->application->user->email,
                ],
            ])->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function topConsumers(bool $providerConnected): array
    {
        if (! $providerConnected) {
            return [];
        }

        return Application::with('user')->where('status', 'RUNNING')->latest()->limit(10)->get()
            ->map(function (Application $application): ?array {
                try {
                    $usage = Cache::remember('admin-overview:application-usage:'.$application->id, now()->addSeconds(15), fn (): array => $this->provider->usage($application));
                } catch (Throwable $exception) {
                    report($exception);

                    return null;
                }

                $memoryPercent = $application->memory_limit_mb > 0 ? $usage['memory_mb'] / $application->memory_limit_mb * 100 : 0;
                $diskPercent = $application->disk_limit_mb > 0 ? $usage['disk_mb'] / $application->disk_limit_mb * 100 : 0;

                return [
                    'application' => ['id' => $application->id, 'name' => $application->name, 'owner' => $application->user->email],
                    'usage' => ['cpu_percent' => $usage['cpu'], 'memory_mb' => $usage['memory_mb'], 'disk_mb' => $usage['disk_mb']],
                    'limits' => ['cpu' => (float) $application->cpu_limit, 'memory_mb' => $application->memory_limit_mb, 'disk_mb' => $application->disk_limit_mb],
                    'highest_utilization_percent' => round(max((float) $usage['cpu'], $memoryPercent, $diskPercent), 1),
                ];
            })->filter()->sortByDesc('highest_utilization_percent')->take(5)->values()->all();
    }

    /** @return array{available: false, cpu_percent: null, memory_used_gb: null, memory_total_gb: null, disk_used_percent: null, disk_total_gb: null, uptime_seconds: null, message: string} */
    private function unavailableHostMetrics(): array
    {
        return ['available' => false, 'cpu_percent' => null, 'memory_used_gb' => null, 'memory_total_gb' => null, 'disk_used_percent' => null, 'disk_total_gb' => null, 'uptime_seconds' => null, 'message' => 'Host metrics are unavailable while the deployment provider is disconnected.'];
    }
}
