<?php

namespace App\Providers;

use App\Contracts\DeploymentProvider;
use App\Models\Application;
use App\Models\Deployment;
use App\Models\Node;
use Illuminate\Support\Str;

class FakeDeploymentProvider implements DeploymentProvider
{
    public function createApplication(Node $node, Application $application): array
    {
        return ['provider_application_id' => 'local_'.$application->id, 'domain' => $application->slug.'.'.config('services.platform_domain')];
    }

    public function updateApplication(Application $application): void {}

    public function deploy(Node $node, Application $application, Deployment $deployment): array
    {
        return ['provider_deployment_id' => 'deploy_'.Str::lower(Str::random(12)), 'commit_sha' => Str::lower(Str::random(8)), 'logs' => implode("\n", [
            '[vive] Repository validated: '.$application->repository_url,
            '[vive] Building branch '.$deployment->branch,
            '[vive] Resource limits: '.$application->memory_limit_mb.' MB RAM / '.$application->cpu_limit.' CPU',
            '[vive] Release completed successfully.',
        ])];
    }

    public function deploymentStatus(Application $application, Deployment $deployment): array
    {
        if ($deployment->provider_deployment_id === null) {
            return ['state' => 'missing', 'provider_deployment_id' => null, 'logs' => $deployment->build_logs ?? ''];
        }

        return ['state' => 'succeeded', 'provider_deployment_id' => $deployment->provider_deployment_id, 'logs' => $deployment->build_logs ?? '[vive] Recovered completed local deployment.'];
    }

    public function restart(Application $application): void {}

    public function stop(Application $application): void {}

    public function delete(Application $application): void {}

    public function runtimeLogs(Application $application, int $tail = 200): string
    {
        return "[vive] {$application->name} is healthy\n[vive] Listening on the assigned platform port";
    }

    public function setEnvironmentVariables(Application $application, array $variables): void {}

    public function addDomain(Application $application, string $domain): array
    {
        return ['status' => 'ACTIVE', 'ssl_status' => 'ACTIVE'];
    }

    public function removeDomain(Application $application, string $domain): void {}

    public function createDatabase(Application $application, string $type, string $databaseName, string $databaseUser, string $password): array
    {
        return ['provider_database_id' => 'local-db-'.$application->id, 'host' => 'database.internal', 'port' => $type === 'POSTGRESQL' ? 5432 : 3306];
    }

    public function deleteDatabase(Application $application, string $providerDatabaseId): void {}

    public function usage(Application $application): array
    {
        return ['cpu' => 0.0, 'memory_mb' => 0, 'disk_mb' => 0];
    }

    public function hostMetrics(Node $node): array
    {
        return ['available' => true, 'cpu_percent' => 0.0, 'memory_used_gb' => 0.0, 'memory_total_gb' => 0.0, 'disk_used_percent' => 0.0, 'disk_total_gb' => 0.0, 'uptime_seconds' => 0, 'message' => 'Local simulation metrics are ready.'];
    }

    public function health(): array
    {
        return ['connected' => true, 'message' => 'Local simulation provider is ready.'];
    }
}
