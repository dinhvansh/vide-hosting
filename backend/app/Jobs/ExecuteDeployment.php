<?php

namespace App\Jobs;

use App\Contracts\DeploymentProvider;
use App\Exceptions\PlatformException;
use App\Models\Deployment;
use App\Services\ApplicationLogRedactor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class ExecuteDeployment implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public int $tries = 120;

    public int $timeout = 1800;

    public function __construct(public string $deploymentId) {}

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('global-deployment-build'))->releaseAfter(10)->expireAfter(2100)->shared()];
    }

    /**
     * Execute the job.
     */
    public function handle(DeploymentProvider $provider, ApplicationLogRedactor $redactor): void
    {
        $deployment = Deployment::with('application.node')->findOrFail($this->deploymentId);
        $claimed = Deployment::query()->whereKey($deployment->id)->where('status', 'QUEUED')->update(['status' => 'BUILDING', 'build_started_at' => now()]);
        if ($claimed !== 1) {
            return;
        }
        $deployment->refresh();
        try {
            $deployment->update(['status' => 'DEPLOYING', 'deploy_started_at' => now()]);
            $result = $provider->deploy($deployment->application->node, $deployment->application, $deployment);
            $deployment->update(['status' => 'RUNNING', 'provider_deployment_id' => $result['provider_deployment_id'], 'commit_sha' => $result['commit_sha'], 'build_logs' => $redactor->redact($deployment->application, $result['logs']), 'finished_at' => now()]);
            $deployment->application->update(['status' => 'RUNNING']);
        } catch (\Throwable $exception) {
            $errorCode = $exception instanceof PlatformException ? $exception->errorCode : 'DEPLOY_FAILED';
            $errorMessage = $exception instanceof PlatformException ? $exception->getMessage() : 'Deployment provider could not complete the release.';
            $failureLogs = $exception instanceof PlatformException ? ($exception->details['logs'] ?? null) : null;
            $deployment->update([
                'status' => 'FAILED',
                'error_code' => $errorCode,
                'error_message' => $errorMessage,
                'build_logs' => is_string($failureLogs) && $failureLogs !== ''
                    ? $redactor->redact($deployment->application, $failureLogs)
                    : $deployment->build_logs,
                'finished_at' => now(),
            ]);
            $deployment->application->update(['status' => 'FAILED']);
            $this->fail($exception);
        }
    }
}
