<?php

namespace Tests\Unit;

use App\Exceptions\ProviderException;
use App\Models\Application;
use App\Models\Deployment;
use App\Models\Node;
use App\Models\ProviderResource;
use App\Providers\DokployProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DokployProviderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.dokploy.url' => 'https://dokploy.test',
            'services.dokploy.token' => 'secret-api-key',
            'services.dokploy.poll_interval' => 0,
            'services.dokploy.deployment_timeout' => 1,
            'services.dokploy.railpack_version' => '0.15.4',
            'services.platform_domain' => 'apps.example.test',
        ]);
    }

    public function test_it_creates_and_maps_a_dokploy_application(): void
    {
        Http::fake([
            '*/api/project.create' => Http::response(['project' => ['projectId' => 'project-1'], 'environment' => ['environmentId' => 'environment-1']]),
            '*/api/application.create' => Http::response(['applicationId' => 'application-1', 'appName' => 'app-vive-demo']),
            '*/api/application.saveGitProvider' => Http::response([]),
            '*/api/application.saveBuildType' => Http::response([]),
            '*/api/application.update' => Http::response([]),
            '*/api/domain.create' => Http::response(['domainId' => 'domain-1']),
        ]);
        $node = Node::factory()->create(['provider' => 'DOKPLOY', 'provider_server_id' => 'server-vps-02']);
        $application = Application::factory()->create(['node_id' => $node->id, 'name' => 'Vive Demo', 'slug' => 'vive-demo', 'repository_url' => 'https://github.com/acme/demo', 'branch' => 'main', 'framework' => 'static']);

        $result = $this->provider()->createApplication($application->node, $application);

        $this->assertSame('application-1', $result['provider_application_id']);
        $this->assertSame('vive-demo.apps.example.test', $result['domain']);
        $this->assertDatabaseHas('provider_resources', ['application_id' => $application->id, 'resource_type' => 'project', 'provider_resource_id' => 'project-1']);
        $this->assertDatabaseHas('provider_resources', ['application_id' => $application->id, 'resource_type' => 'environment', 'provider_resource_id' => 'environment-1']);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/application.saveGitProvider'
            && $request->header('x-api-key')[0] === 'secret-api-key'
            && $request['customGitUrl'] === 'https://github.com/acme/demo');
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/api/application.update')
            && $request['memoryLimit'] === '536870912'
            && $request['cpuLimit'] === '500000000');
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/api/application.create')
            && $request['serverId'] === 'server-vps-02');
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/api/domain.create') && $request['port'] === 80);
    }

    public function test_it_compensates_when_application_creation_fails(): void
    {
        Http::fake([
            '*/api/project.create' => Http::response(['project' => ['projectId' => 'project-orphan'], 'environment' => ['environmentId' => 'environment-1']]),
            '*/api/application.create' => Http::response(['unexpected' => true]),
            '*/api/project.remove' => Http::response([]),
        ]);

        try {
            $application = Application::factory()->create();
            $this->provider()->createApplication($application->node, $application);
            $this->fail('Expected a provider exception.');
        } catch (ProviderException $exception) {
            $this->assertSame('PROVIDER_INVALID_RESPONSE', $exception->errorCode);
        }
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/api/project.remove') && $request['projectId'] === 'project-orphan');
    }

    public function test_it_deploys_waits_for_completion_and_reads_logs(): void
    {
        $application = Application::factory()->create(['provider_application_id' => 'application-1']);
        $deployment = Deployment::factory()->create(['application_id' => $application->id]);
        Http::fake([
            '*/api/application.deploy' => Http::response([]),
            '*/api/deployment.allByType*' => Http::sequence()
                ->push([['deploymentId' => 'deployment-1', 'status' => 'running', 'title' => 'Vive deployment '.$deployment->id]])
                ->push([['deploymentId' => 'deployment-1', 'status' => 'done', 'title' => 'Vive deployment '.$deployment->id]]),
            '*/api/deployment.readLogs*' => Http::response('build complete', 200, ['Content-Type' => 'text/plain']),
        ]);

        $result = $this->provider()->deploy($application->node, $application, $deployment);

        $this->assertSame('deployment-1', $result['provider_deployment_id']);
        $this->assertSame('build complete', $result['logs']);
        $this->assertSame('deployment-1', $deployment->fresh()->provider_deployment_id);
    }

    public function test_it_includes_provider_logs_when_a_deployment_fails(): void
    {
        $application = Application::factory()->create(['provider_application_id' => 'application-1']);
        $deployment = Deployment::factory()->create(['application_id' => $application->id]);
        Http::fake([
            '*/api/application.deploy' => Http::response([]),
            '*/api/deployment.allByType*' => Http::response([[
                'deploymentId' => 'deployment-failed',
                'status' => 'error',
                'title' => 'Vive deployment '.$deployment->id,
            ]]),
            '*/api/deployment.readLogs*' => Http::response('railpack build failed', 200, ['Content-Type' => 'text/plain']),
        ]);

        try {
            $this->provider()->deploy($application->node, $application, $deployment);
            $this->fail('Expected a provider exception.');
        } catch (ProviderException $exception) {
            $this->assertSame('DEPLOY_FAILED', $exception->errorCode);
            $this->assertSame('deployment-failed', $exception->details['provider_deployment_id']);
            $this->assertSame('railpack build failed', $exception->details['logs']);
        }
        $this->assertSame('deployment-failed', $deployment->fresh()->provider_deployment_id);
    }

    public function test_it_syncs_environment_and_managed_postgres(): void
    {
        Http::fake([
            '*/api/application.saveEnvironment' => Http::response([]),
            '*/api/postgres.create' => Http::response(['postgresId' => 'postgres-1', 'appName' => 'postgres-vive-db']),
            '*/api/postgres.deploy' => Http::response([]),
        ]);
        $application = Application::factory()->create(['provider_application_id' => 'application-1']);
        ProviderResource::factory()->create(['application_id' => $application->id, 'resource_type' => 'environment', 'local_reference' => 'production', 'provider_resource_id' => 'environment-1']);

        $this->provider()->setEnvironmentVariables($application, ['ZED' => 'line one', 'ALPHA' => 'a"b']);
        $database = $this->provider()->createDatabase($application, 'POSTGRESQL', 'vive_db', 'vive_user', 'password');

        $this->assertSame('postgres:postgres-1', $database['provider_database_id']);
        $this->assertSame('postgres-vive-db', $database['host']);
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/api/application.saveEnvironment') && $request['env'] === "ALPHA=\"a\\\"b\"\nZED=\"line one\"");
    }

    public function test_it_deletes_the_application_service_before_project_metadata(): void
    {
        Http::fake([
            '*/api/postgres.remove' => Http::response([]),
            '*/api/application.delete' => Http::response([]),
            '*/api/project.remove' => Http::response([]),
        ]);
        $application = Application::factory()->create(['provider_application_id' => 'application-1']);
        $application->databases()->create(['type' => 'POSTGRESQL', 'database_name' => 'vive', 'database_user' => 'vive', 'encrypted_password' => 'secret', 'host' => 'database.internal', 'port' => 5432, 'provider_database_id' => 'postgres:database-1', 'status' => 'RUNNING']);
        ProviderResource::factory()->create(['application_id' => $application->id, 'resource_type' => 'project', 'local_reference' => 'primary', 'provider_resource_id' => 'project-1']);

        $this->provider()->delete($application);

        $requests = Http::recorded();
        $this->assertStringEndsWith('/api/postgres.remove', $requests[0][0]->url());
        $this->assertStringEndsWith('/api/application.delete', $requests[1][0]->url());
        $this->assertStringEndsWith('/api/project.remove', $requests[2][0]->url());
    }

    public function test_it_updates_git_branch_and_build_configuration(): void
    {
        Http::fake([
            '*/api/application.saveGitProvider' => Http::response([]),
            '*/api/application.saveBuildType' => Http::response([]),
        ]);
        $application = Application::factory()->create([
            'provider_application_id' => 'application-1',
            'repository_url' => 'https://github.com/acme/updated',
            'branch' => 'release',
            'framework' => 'static',
        ]);

        $this->provider()->updateApplication($application);

        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/api/application.saveGitProvider') && $request['customGitBranch'] === 'release');
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/api/application.saveBuildType') && $request['buildType'] === 'static');
    }

    public function test_it_configures_non_static_apps_with_a_valid_railpack_version(): void
    {
        Http::fake([
            '*/api/application.saveGitProvider' => Http::response([]),
            '*/api/application.saveBuildType' => Http::response([]),
        ]);
        $application = Application::factory()->create([
            'provider_application_id' => 'application-1',
            'framework' => 'nextjs',
        ]);

        $this->provider()->updateApplication($application);

        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/api/application.saveBuildType')
            && $request['buildType'] === 'railpack'
            && $request['railpackVersion'] === '0.15.4');
    }

    public function test_it_routes_laravel_apps_to_the_frankenphp_port(): void
    {
        Http::fake(['*/api/domain.create' => Http::response(['domainId' => 'domain-laravel'])]);
        $application = Application::factory()->create([
            'provider_application_id' => 'application-laravel',
            'framework' => 'laravel',
        ]);

        $this->provider()->addDomain($application, 'laravel.example.test');

        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/api/domain.create')
            && $request['port'] === 80);
    }

    public function test_provider_errors_are_redacted_and_normalized(): void
    {
        Http::fake(['*' => Http::response(['message' => 'sensitive upstream details'], 401)]);
        $application = Application::factory()->create(['provider_application_id' => 'application-1']);

        try {
            $this->provider()->stop($application);
            $this->fail('Expected a provider exception.');
        } catch (ProviderException $exception) {
            $this->assertSame('PROVIDER_AUTH_FAILED', $exception->errorCode);
            $this->assertStringNotContainsString('sensitive', $exception->getMessage());
        }
    }

    public function test_it_normalizes_dokploy_host_metrics_without_exposing_monitoring_token(): void
    {
        $node = Node::factory()->create(['provider' => 'DOKPLOY', 'provider_server_id' => 'server-1']);
        Http::fake([
            '*/api/server.all*' => Http::response([[
                'serverId' => 'server-1',
                'serverType' => 'web',
                'ipAddress' => '10.0.0.20',
                'metricsConfig' => ['server' => ['port' => 4500, 'token' => 'monitor-secret']],
            ]]),
            '*/api/server.getServerMetrics*' => Http::response([[
                'cpu' => '12.5',
                'memUsedGB' => '3.2',
                'memTotal' => '8',
                'diskUsed' => '44.1',
                'totalDisk' => '100',
                'uptime' => 3600,
            ]]),
        ]);

        $metrics = $this->provider()->hostMetrics($node);

        $this->assertTrue($metrics['available']);
        $this->assertSame(12.5, $metrics['cpu_percent']);
        $this->assertSame(3.2, $metrics['memory_used_gb']);
        $this->assertSame(44.1, $metrics['disk_used_percent']);
        $this->assertStringNotContainsString('monitor-secret', json_encode($metrics, JSON_THROW_ON_ERROR));
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/api/server.getServerMetrics')
            && str_contains($request->url(), 'dataPoints=1')
            && str_contains(urldecode($request->url()), 'http://10.0.0.20:4500/metrics'));
    }

    private function provider(): DokployProvider
    {
        return app(DokployProvider::class);
    }
}
