<?php

namespace App\Providers;

use App\Contracts\DeploymentProvider;
use App\Exceptions\ProviderException;
use App\Models\Application;
use App\Models\Deployment;
use App\Models\Node;
use App\Models\ProviderResource;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class DokployProvider implements DeploymentProvider
{
    public function createApplication(Node $node, Application $application): array
    {
        $this->assertApplicationNode($node, $application);
        $projectId = null;
        $applicationId = null;

        try {
            $project = $this->post('project.create', ['name' => 'Vive - '.$application->name, 'description' => 'Managed by Vive Host ('.$application->id.')']);
            $projectId = $this->requiredString($project, 'project.projectId', 'project.create');
            $environmentId = $this->requiredString($project, 'environment.environmentId', 'project.create');
            $createdApplication = $this->post('application.create', array_filter([
                'name' => $application->name,
                'appName' => $application->slug,
                'description' => 'Managed by Vive Host',
                'environmentId' => $environmentId,
                'serverId' => $node->provider_server_id,
            ], fn (mixed $value): bool => $value !== null && $value !== ''));
            $applicationId = $this->requiredString($createdApplication, 'applicationId', 'application.create');
            $appName = $this->requiredString($createdApplication, 'appName', 'application.create');

            $this->saveGitConfiguration($application, $applicationId);
            $this->configureBuild($application, $applicationId);
            $this->post('application.update', [
                'applicationId' => $applicationId,
                'memoryLimit' => (string) ($application->memory_limit_mb * 1024 * 1024),
                'memoryReservation' => (string) ($application->memory_limit_mb * 1024 * 1024),
                'cpuLimit' => (string) ((float) $application->cpu_limit * 1_000_000_000),
                'cpuReservation' => (string) ((float) $application->cpu_limit * 1_000_000_000),
                'restartPolicySwarm' => ['Condition' => 'on-failure', 'Delay' => 5_000_000_000, 'MaxAttempts' => 5, 'Window' => 120_000_000_000],
                'ulimitsSwarm' => [['Name' => 'nproc', 'Soft' => 256, 'Hard' => 256]],
            ]);

            $domainName = $application->slug.'.'.config('services.platform_domain');
            $domain = $this->post('domain.create', [
                'host' => $domainName,
                'https' => true,
                'port' => $this->applicationPort($application),
                'applicationId' => $applicationId,
                'certificateType' => 'letsencrypt',
                'domainType' => 'application',
            ]);

            $this->storeResource($application, 'project', 'primary', $projectId);
            $this->storeResource($application, 'environment', 'production', $environmentId);
            $this->storeResource($application, 'application', 'primary', $applicationId, ['app_name' => $appName]);
            $this->storeResource($application, 'domain', $domainName, $this->requiredString($domain, 'domainId', 'domain.create'));

            return ['provider_application_id' => $applicationId, 'domain' => $domainName];
        } catch (Throwable $exception) {
            $this->compensateFailedApplicationCreation($projectId, $applicationId);
            throw $this->normalizeException($exception);
        }
    }

    public function updateApplication(Application $application): void
    {
        $applicationId = $this->applicationId($application);
        $this->saveGitConfiguration($application, $applicationId);
        $this->configureBuild($application, $applicationId);
    }

    public function deploy(Node $node, Application $application, Deployment $deployment): array
    {
        $this->assertApplicationNode($node, $application);
        $applicationId = $this->applicationId($application);
        $this->post('application.deploy', ['applicationId' => $applicationId, 'title' => 'Vive deployment '.$deployment->id, 'description' => 'Branch: '.$deployment->branch]);
        $providerDeployment = $this->waitForDeployment($applicationId, $deployment);
        $providerDeploymentId = $this->requiredString($providerDeployment, 'deploymentId', 'deployment.allByType');
        $logs = $this->get('deployment.readLogs', ['deploymentId' => $providerDeploymentId, 'tail' => 10000]);

        return ['provider_deployment_id' => $providerDeploymentId, 'commit_sha' => null, 'logs' => is_string($logs) ? $logs : ''];
    }

    public function deploymentStatus(Application $application, Deployment $deployment): array
    {
        $candidate = $this->findDeployment($this->applicationId($application), $deployment);
        if ($candidate === null) {
            return ['state' => 'missing', 'provider_deployment_id' => null, 'logs' => $deployment->build_logs ?? ''];
        }

        $providerDeploymentId = $this->requiredString($candidate, 'deploymentId', 'deployment.allByType');
        if ($deployment->provider_deployment_id !== $providerDeploymentId) {
            $deployment->update(['provider_deployment_id' => $providerDeploymentId]);
        }
        $status = $candidate['status'] ?? null;
        $state = match ($status) {
            'done' => 'succeeded',
            'error', 'cancelled' => 'failed',
            default => 'running',
        };
        $logs = $this->get('deployment.readLogs', ['deploymentId' => $providerDeploymentId, 'tail' => 10000]);

        return ['state' => $state, 'provider_deployment_id' => $providerDeploymentId, 'logs' => is_string($logs) ? $logs : ''];
    }

    public function restart(Application $application): void
    {
        $payload = ['applicationId' => $this->applicationId($application)];
        $this->post('application.stop', $payload);
        $this->post('application.start', $payload);
    }

    public function stop(Application $application): void
    {
        $this->post('application.stop', ['applicationId' => $this->applicationId($application)]);
    }

    public function delete(Application $application): void
    {
        foreach ($application->databases()->get() as $database) {
            $this->deleteDatabase($application, $database->provider_database_id);
        }

        $applicationId = $this->applicationId($application);
        $projectId = $this->resourceId($application, 'project', 'primary', required: false);
        $this->post('application.delete', ['applicationId' => $applicationId]);
        if ($projectId !== null) {
            $this->post('project.remove', ['projectId' => $projectId]);
        }
    }

    public function runtimeLogs(Application $application, int $tail = 200): string
    {
        $logs = $this->get('application.readLogs', ['applicationId' => $this->applicationId($application), 'tail' => min(max($tail, 1), 10000), 'since' => 'all']);

        return is_string($logs) ? $logs : '';
    }

    public function setEnvironmentVariables(Application $application, array $variables): void
    {
        ksort($variables);
        $lines = [];
        foreach ($variables as $key => $value) {
            $lines[] = $key.'='.json_encode((string) $value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        $this->post('application.saveEnvironment', ['applicationId' => $this->applicationId($application), 'env' => implode("\n", $lines), 'buildArgs' => null, 'buildSecrets' => null, 'createEnvFile' => true]);
    }

    public function addDomain(Application $application, string $domain): array
    {
        $created = $this->post('domain.create', ['host' => $domain, 'https' => true, 'port' => $this->applicationPort($application), 'applicationId' => $this->applicationId($application), 'certificateType' => 'letsencrypt', 'domainType' => 'application']);
        $this->storeResource($application, 'domain', $domain, $this->requiredString($created, 'domainId', 'domain.create'));

        return ['status' => 'ACTIVE', 'ssl_status' => 'PENDING'];
    }

    public function removeDomain(Application $application, string $domain): void
    {
        $resource = $this->resource($application, 'domain', $domain);
        $this->post('domain.delete', ['domainId' => $resource->provider_resource_id]);
        $resource->delete();
    }

    public function createDatabase(Application $application, string $type, string $databaseName, string $databaseUser, string $password): array
    {
        $environmentId = $this->resourceId($application, 'environment', 'production');
        $isPostgres = $type === 'POSTGRESQL';
        $prefix = $isPostgres ? 'postgres' : 'mysql';
        $created = $this->post($prefix.'.create', array_filter([
            'name' => $application->name.' database',
            'appName' => $application->slug.'-db',
            'databaseName' => $databaseName,
            'databaseUser' => $databaseUser,
            'databasePassword' => $password,
            'databaseRootPassword' => $isPostgres ? null : $password,
            'environmentId' => $environmentId,
            'serverId' => $application->node()->value('provider_server_id'),
        ], fn (mixed $value): bool => $value !== null && $value !== ''));
        $idKey = $isPostgres ? 'postgresId' : 'mysqlId';
        $providerDatabaseId = $this->requiredString($created, $idKey, $prefix.'.create');
        $host = $this->requiredString($created, 'appName', $prefix.'.create');
        $this->post($prefix.'.deploy', [$idKey => $providerDatabaseId]);

        return ['provider_database_id' => $prefix.':'.$providerDatabaseId, 'host' => $host, 'port' => $isPostgres ? 5432 : 3306];
    }

    public function deleteDatabase(Application $application, string $providerDatabaseId): void
    {
        [$type, $id] = $this->parseDatabaseId($providerDatabaseId);
        $this->post($type.'.remove', [$type.'Id' => $id]);
    }

    public function usage(Application $application): array
    {
        $metadata = $this->resource($application, 'application', 'primary')->metadata ?? [];
        $stats = $this->get('application.readAppMonitoring', ['appName' => $metadata['app_name'] ?? $application->slug]);
        if (! is_array($stats)) {
            return ['cpu' => 0.0, 'memory_mb' => 0, 'disk_mb' => 0];
        }

        return [
            'cpu' => $this->lastNumericMetric($stats['cpu'] ?? null),
            'memory_mb' => (int) round($this->lastNumericMetric($stats['memory'] ?? null) / 1024 / 1024),
            'disk_mb' => (int) round($this->lastNumericMetric($stats['disk'] ?? null) / 1024 / 1024),
        ];
    }

    public function hostMetrics(Node $node): array
    {
        try {
            $servers = $this->get('server.all');
            if (! is_array($servers)) {
                return $this->unavailableHostMetrics('Dokploy did not return a server list.');
            }

            $configuredServerId = $node->provider_server_id;
            $server = collect($servers)->first(function (mixed $candidate) use ($configuredServerId): bool {
                if (! is_array($candidate)) {
                    return false;
                }

                return is_string($configuredServerId) && $configuredServerId !== ''
                    ? ($candidate['serverId'] ?? null) === $configuredServerId
                    : ($candidate['serverType'] ?? 'web') !== 'build';
            });
            if (! is_array($server)) {
                return $this->unavailableHostMetrics('No matching Dokploy web server was found.');
            }

            $metricsConfig = $server['metricsConfig'] ?? [];
            if (is_string($metricsConfig)) {
                $metricsConfig = json_decode($metricsConfig, true) ?: [];
            }
            $serverMetrics = is_array($metricsConfig) ? ($metricsConfig['server'] ?? []) : [];
            $ipAddress = $server['ipAddress'] ?? null;
            $port = is_array($serverMetrics) ? ($serverMetrics['port'] ?? null) : null;
            $token = is_array($serverMetrics) ? ($serverMetrics['token'] ?? null) : null;
            if (! is_string($ipAddress) || $ipAddress === '' || ! is_numeric($port) || ! is_string($token) || $token === '') {
                return $this->unavailableHostMetrics('Dokploy host monitoring is not configured.');
            }

            $metrics = $this->get('server.getServerMetrics', [
                'url' => 'http://'.$ipAddress.':'.(int) $port.'/metrics',
                'token' => $token,
                'dataPoints' => '1',
            ]);
            $latest = is_array($metrics) ? end($metrics) : false;
            if (! is_array($latest)) {
                return $this->unavailableHostMetrics('Dokploy host monitoring has no samples yet.');
            }

            return [
                'available' => true,
                'cpu_percent' => (float) ($latest['cpu'] ?? 0),
                'memory_used_gb' => (float) ($latest['memUsedGB'] ?? 0),
                'memory_total_gb' => (float) ($latest['memTotal'] ?? 0),
                'disk_used_percent' => (float) ($latest['diskUsed'] ?? 0),
                'disk_total_gb' => (float) ($latest['totalDisk'] ?? 0),
                'uptime_seconds' => (int) ($latest['uptime'] ?? 0),
                'message' => 'Dokploy host monitoring is reporting.',
            ];
        } catch (Throwable $exception) {
            report($exception);

            return $this->unavailableHostMetrics('Dokploy host monitoring is unavailable.');
        }
    }

    public function health(): array
    {
        try {
            $this->get('project.all');

            return ['connected' => true, 'message' => 'Dokploy API is reachable and authenticated.'];
        } catch (Throwable $exception) {
            report($exception);

            return ['connected' => false, 'message' => 'Dokploy API is unavailable or authentication failed.'];
        }
    }

    /** @return array{available: false, cpu_percent: null, memory_used_gb: null, memory_total_gb: null, disk_used_percent: null, disk_total_gb: null, uptime_seconds: null, message: string} */
    private function unavailableHostMetrics(string $message): array
    {
        return ['available' => false, 'cpu_percent' => null, 'memory_used_gb' => null, 'memory_total_gb' => null, 'disk_used_percent' => null, 'disk_total_gb' => null, 'uptime_seconds' => null, 'message' => $message];
    }

    private function assertApplicationNode(Node $node, Application $application): void
    {
        if ($application->node_id !== $node->id) {
            throw new ProviderException('PROVIDER_NODE_MISMATCH', 'The application node context is invalid.', 409);
        }
    }

    private function pendingRequest(bool $safeToRetry = false): PendingRequest
    {
        $url = config('services.dokploy.url');
        $token = config('services.dokploy.token');
        if (! is_string($url) || trim($url) === '' || ! is_string($token) || trim($token) === '') {
            throw new ProviderException('PROVIDER_UNAVAILABLE', 'Dokploy URL and API key must be configured.');
        }
        $baseUrl = rtrim($url, '/');
        $request = Http::baseUrl($baseUrl.(str_ends_with($baseUrl, '/api') ? '' : '/api'))
            ->acceptJson()->asJson()
            ->withHeaders(['x-api-key' => $token, 'X-Request-ID' => request()?->attributes->get('request_id', (string) Str::uuid())])
            ->connectTimeout(config('services.dokploy.connect_timeout'))->timeout(config('services.dokploy.timeout'));
        if ($safeToRetry) {
            $request->retry([200, 500], when: fn (Throwable $exception): bool => $exception instanceof ConnectionException || ($exception instanceof RequestException && $exception->response->serverError()));
        }

        return $request;
    }

    /** @param array<string, mixed> $query */
    private function get(string $endpoint, array $query = []): mixed
    {
        try {
            return $this->decode($this->pendingRequest(true)->get($endpoint, $query), $endpoint);
        } catch (Throwable $exception) {
            throw $this->normalizeException($exception);
        }
    }

    /** @param array<string, mixed> $payload */
    private function post(string $endpoint, array $payload = []): mixed
    {
        try {
            return $this->decode($this->pendingRequest()->post($endpoint, $payload), $endpoint);
        } catch (Throwable $exception) {
            throw $this->normalizeException($exception);
        }
    }

    private function decode(Response $response, string $endpoint): mixed
    {
        if ($response->failed()) {
            $status = $response->status();
            $code = match (true) {
                $status === 401, $status === 403 => 'PROVIDER_AUTH_FAILED',
                $status === 404 => 'PROVIDER_RESOURCE_NOT_FOUND',
                $status === 409 => 'PROVIDER_CONFLICT',
                $status === 422, $status === 400 => 'PROVIDER_REJECTED',
                $status === 429 => 'PROVIDER_RATE_LIMITED',
                default => 'PROVIDER_UNAVAILABLE',
            };
            $httpStatus = in_array($status, [400, 404, 409, 422, 429], true) ? $status : 503;
            throw new ProviderException($code, 'Dokploy could not complete the requested operation.', $httpStatus, ['operation' => $endpoint, 'provider_status' => $status]);
        }
        if (str_contains(strtolower($response->header('Content-Type')), 'json')) {
            return $response->json();
        }

        return $response->body();
    }

    private function normalizeException(Throwable $exception): ProviderException
    {
        return $exception instanceof ProviderException ? $exception : new ProviderException(previous: $exception);
    }

    /** @return array<string, mixed> */
    private function waitForDeployment(string $applicationId, Deployment $deployment): array
    {
        $deadline = microtime(true) + config('services.dokploy.deployment_timeout');
        do {
            $candidate = $this->findDeployment($applicationId, $deployment);
            if ($candidate !== null) {
                $providerDeploymentId = $this->requiredString($candidate, 'deploymentId', 'deployment.allByType');
                if ($deployment->provider_deployment_id !== $providerDeploymentId) {
                    $deployment->update(['provider_deployment_id' => $providerDeploymentId]);
                }
                $status = $candidate['status'] ?? null;
                if ($status === 'done') {
                    return $candidate;
                }
                if (in_array($status, ['error', 'cancelled'], true)) {
                    throw new ProviderException('DEPLOY_FAILED', 'Dokploy reported that the deployment failed.', 422, [
                        'provider_deployment_id' => $providerDeploymentId,
                        'logs' => $this->deploymentLogs($providerDeploymentId),
                    ]);
                }
            }
            $pollInterval = max((int) config('services.dokploy.poll_interval'), 0);
            if ($pollInterval > 0) {
                sleep($pollInterval);
            }
        } while (microtime(true) < $deadline);

        throw new ProviderException('DEPLOY_TIMEOUT', 'Dokploy did not finish the deployment before the timeout.', 504);
    }

    /** @return array<string, mixed>|null */
    private function findDeployment(string $applicationId, Deployment $deployment): ?array
    {
        $deployments = $this->get('deployment.allByType', ['id' => $applicationId, 'type' => 'application']);
        if (! is_array($deployments)) {
            return null;
        }
        $expectedTitle = 'Vive deployment '.$deployment->id;
        foreach ($deployments as $candidate) {
            if (! is_array($candidate)) {
                continue;
            }
            if ($deployment->provider_deployment_id !== null && ($candidate['deploymentId'] ?? null) === $deployment->provider_deployment_id) {
                return $candidate;
            }
            if (($candidate['title'] ?? null) === $expectedTitle) {
                return $candidate;
            }
        }

        return null;
    }

    private function applicationId(Application $application): string
    {
        if (is_string($application->provider_application_id) && $application->provider_application_id !== '') {
            return $application->provider_application_id;
        }

        return $this->resourceId($application, 'application', 'primary');
    }

    private function resource(Application $application, string $type, string $localReference): ProviderResource
    {
        $resource = $application->providerResources()->where('provider', 'dokploy')->where('resource_type', $type)->where('local_reference', $localReference)->first();
        if (! $resource) {
            throw new ProviderException('PROVIDER_MAPPING_MISSING', 'The Dokploy resource mapping is missing.', 409, ['resource_type' => $type]);
        }

        return $resource;
    }

    private function resourceId(Application $application, string $type, string $localReference, bool $required = true): ?string
    {
        if (! $required) {
            return $application->providerResources()->where('provider', 'dokploy')->where('resource_type', $type)->where('local_reference', $localReference)->value('provider_resource_id');
        }

        return $this->resource($application, $type, $localReference)->provider_resource_id;
    }

    /** @param array<string, mixed> $metadata */
    private function storeResource(Application $application, string $type, string $localReference, string $providerId, array $metadata = []): void
    {
        $application->providerResources()->updateOrCreate(
            ['provider' => 'dokploy', 'resource_type' => $type, 'local_reference' => $localReference],
            ['provider_resource_id' => $providerId, 'metadata' => $metadata],
        );
    }

    private function requiredString(mixed $payload, string $path, string $operation): string
    {
        $value = is_array($payload) ? data_get($payload, $path) : null;
        if (! is_string($value) || $value === '') {
            throw new ProviderException('PROVIDER_INVALID_RESPONSE', 'Dokploy returned an unexpected response.', 502, ['operation' => $operation, 'missing' => $path]);
        }

        return $value;
    }

    private function compensateFailedApplicationCreation(?string $projectId, ?string $applicationId): void
    {
        try {
            if ($projectId !== null) {
                $this->post('project.remove', ['projectId' => $projectId]);
            } elseif ($applicationId !== null) {
                $this->post('application.delete', ['applicationId' => $applicationId]);
            }
        } catch (Throwable) {
            // The original provider failure is more actionable; orphan cleanup is reconciled operationally.
        }
    }

    private function configureBuild(Application $application, string $applicationId): void
    {
        $isStatic = $application->framework === 'static';
        $this->post('application.saveBuildType', [
            'applicationId' => $applicationId,
            'buildType' => $isStatic ? 'static' : 'railpack',
            'dockerfile' => null,
            'dockerContextPath' => null,
            'dockerBuildStage' => null,
            'herokuVersion' => null,
            'railpackVersion' => $isStatic ? null : config('services.dokploy.railpack_version'),
            'publishDirectory' => $isStatic ? '/' : null,
            'isStaticSpa' => $isStatic,
        ]);
    }

    private function deploymentLogs(string $providerDeploymentId): string
    {
        try {
            $logs = $this->get('deployment.readLogs', ['deploymentId' => $providerDeploymentId, 'tail' => 10000]);

            return is_string($logs) ? $logs : '';
        } catch (Throwable) {
            return '';
        }
    }

    private function applicationPort(Application $application): int
    {
        return in_array($application->framework, ['static', 'laravel'], true) ? 80 : 3000;
    }

    private function saveGitConfiguration(Application $application, string $applicationId): void
    {
        $this->post('application.saveGitProvider', [
            'applicationId' => $applicationId,
            'customGitBuildPath' => null,
            'customGitUrl' => $application->repository_url,
            'watchPaths' => null,
            'enableSubmodules' => false,
            'customGitBranch' => $application->branch,
            'customGitSSHKeyId' => null,
        ]);
    }

    /** @return array{string, string} */
    private function parseDatabaseId(string $providerDatabaseId): array
    {
        $parts = explode(':', $providerDatabaseId, 2);
        if (count($parts) !== 2 || ! in_array($parts[0], ['postgres', 'mysql'], true) || $parts[1] === '') {
            throw new ProviderException('PROVIDER_MAPPING_MISSING', 'The Dokploy database mapping is invalid.', 409);
        }

        return [$parts[0], $parts[1]];
    }

    private function lastNumericMetric(mixed $series): float
    {
        if (! is_array($series) || $series === []) {
            return 0.0;
        }
        $value = array_is_list($series) ? end($series) : $series;
        if (is_array($value)) {
            $value = $value['value'] ?? $value['usage'] ?? $value['memoryUsage'] ?? $value['diskUsage'] ?? 0;
        }
        if (is_string($value)) {
            $value = rtrim($value, '% MBGBKiBMiB ');
        }

        return is_numeric($value) ? (float) $value : 0.0;
    }
}
