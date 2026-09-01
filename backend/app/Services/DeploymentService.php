<?php

namespace App\Services;

use App\Jobs\ExecuteDeployment;
use App\Models\Application;
use App\Models\Deployment;
use App\Models\User;

class DeploymentService
{
    public function __construct(private QuotaService $quotas) {}

    /** @return array{deployment: Deployment, created: bool} */
    public function create(Application $application, User $actor, ?string $branch = null, ?string $idempotencyKey = null): array
    {
        if ($idempotencyKey !== null) {
            $existing = $application->deployments()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return ['deployment' => $existing, 'created' => false];
            }
        }

        $this->quotas->assertCanCreateDeployment($application->user);

        $deployment = $application->deployments()->create(['status' => 'QUEUED', 'branch' => $branch ?: $application->branch, 'created_by' => $actor->id, 'idempotency_key' => $idempotencyKey]);
        ExecuteDeployment::dispatch($deployment->id);

        return ['deployment' => $deployment, 'created' => true];
    }
}
