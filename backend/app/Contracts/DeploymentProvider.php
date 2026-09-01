<?php

namespace App\Contracts;

use App\Models\Application;
use App\Models\Deployment;
use App\Models\Node;

interface DeploymentProvider
{
    /** @return array{provider_application_id: string, domain: string} */
    public function createApplication(Node $node, Application $application): array;

    public function updateApplication(Application $application): void;

    /** @return array{provider_deployment_id: string, commit_sha: string|null, logs: string} */
    public function deploy(Node $node, Application $application, Deployment $deployment): array;

    /** @return array{state: 'running'|'succeeded'|'failed'|'missing', provider_deployment_id: string|null, logs: string} */
    public function deploymentStatus(Application $application, Deployment $deployment): array;

    public function restart(Application $application): void;

    public function stop(Application $application): void;

    public function delete(Application $application): void;

    public function runtimeLogs(Application $application, int $tail = 200): string;

    /** @param array<string, string> $variables */
    public function setEnvironmentVariables(Application $application, array $variables): void;

    /** @return array{status: string, ssl_status: string} */
    public function addDomain(Application $application, string $domain): array;

    public function removeDomain(Application $application, string $domain): void;

    /** @return array{provider_database_id: string, host: string, port: int} */
    public function createDatabase(Application $application, string $type, string $databaseName, string $databaseUser, string $password): array;

    public function deleteDatabase(Application $application, string $providerDatabaseId): void;

    /** @return array{cpu: float, memory_mb: int, disk_mb: int} */
    public function usage(Application $application): array;

    /** @return array{available: bool, cpu_percent: float|null, memory_used_gb: float|null, memory_total_gb: float|null, disk_used_percent: float|null, disk_total_gb: float|null, uptime_seconds: int|null, message: string} */
    public function hostMetrics(Node $node): array;

    /** @return array{connected: bool, message: string} */
    public function health(): array;
}
