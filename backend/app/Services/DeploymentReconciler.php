<?php

namespace App\Services;

use App\Contracts\DeploymentProvider;
use App\Jobs\ExecuteDeployment;
use App\Models\Deployment;
use Throwable;

class DeploymentReconciler
{
    public function __construct(private DeploymentProvider $provider, private ApplicationLogRedactor $redactor) {}

    /** @return array{requeued: int, recovered: int, failed: int, pending: int, errors: int} */
    public function reconcile(int $staleMinutes, int $queuedMinutes): array
    {
        $counts = ['requeued' => 0, 'recovered' => 0, 'failed' => 0, 'pending' => 0, 'errors' => 0];
        Deployment::query()->where('status', 'QUEUED')->where('created_at', '<=', now()->subMinutes($queuedMinutes))->each(function (Deployment $deployment) use (&$counts): void {
            ExecuteDeployment::dispatch($deployment->id);
            $counts['requeued']++;
        });

        Deployment::with('application.node')->whereIn('status', ['BUILDING', 'DEPLOYING'])->where('updated_at', '<=', now()->subMinutes($staleMinutes))->each(function (Deployment $deployment) use (&$counts): void {
            try {
                $result = $this->provider->deploymentStatus($deployment->application, $deployment);
                $logs = $this->redactor->redact($deployment->application, $result['logs']);
                if ($result['state'] === 'succeeded') {
                    $deployment->update(['status' => 'RUNNING', 'provider_deployment_id' => $result['provider_deployment_id'], 'build_logs' => $logs, 'finished_at' => now(), 'error_code' => null, 'error_message' => null]);
                    $deployment->application->update(['status' => 'RUNNING']);
                    $counts['recovered']++;

                    return;
                }
                if ($result['state'] === 'running') {
                    $deployment->update(['provider_deployment_id' => $result['provider_deployment_id'], 'build_logs' => $logs]);
                    $counts['pending']++;

                    return;
                }

                $errorCode = $result['state'] === 'missing' ? 'DEPLOYMENT_RECOVERY_NOT_FOUND' : 'DEPLOY_FAILED';
                $message = $result['state'] === 'missing' ? 'The provider no longer reports this deployment.' : 'The provider reported that the deployment failed.';
                $deployment->update(['status' => 'FAILED', 'provider_deployment_id' => $result['provider_deployment_id'], 'build_logs' => $logs, 'error_code' => $errorCode, 'error_message' => $message, 'finished_at' => now()]);
                $deployment->application->update(['status' => 'FAILED']);
                $counts['failed']++;
            } catch (Throwable $exception) {
                report($exception);
                $counts['errors']++;
            }
        });

        return $counts;
    }
}
