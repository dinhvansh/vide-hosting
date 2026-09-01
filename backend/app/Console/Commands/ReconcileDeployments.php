<?php

namespace App\Console\Commands;

use App\Services\DeploymentReconciler;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('deployments:reconcile {--stale-minutes= : Age of in-progress deployments to inspect} {--queued-minutes= : Age of queued deployments to re-dispatch}')]
#[Description('Recover stale and orphaned deployment records')]
class ReconcileDeployments extends Command
{
    public function handle(DeploymentReconciler $reconciler): int
    {
        $staleMinutes = max(1, (int) ($this->option('stale-minutes') ?? config('services.deployments.stale_minutes')));
        $queuedMinutes = max(1, (int) ($this->option('queued-minutes') ?? config('services.deployments.queued_minutes')));
        $counts = $reconciler->reconcile($staleMinutes, $queuedMinutes);
        $this->components->info(sprintf('Requeued: %d; recovered: %d; failed: %d; still running: %d; provider errors: %d.', $counts['requeued'], $counts['recovered'], $counts['failed'], $counts['pending'], $counts['errors']));

        return self::SUCCESS;
    }
}
