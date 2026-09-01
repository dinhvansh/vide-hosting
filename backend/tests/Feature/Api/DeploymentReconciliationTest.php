<?php

namespace Tests\Feature\Api;

use App\Jobs\ExecuteDeployment;
use App\Models\Application;
use App\Models\Deployment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DeploymentReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_stale_queued_deployment_is_safely_re_dispatched(): void
    {
        Queue::fake();
        $deployment = Deployment::factory()->create(['status' => 'QUEUED', 'created_at' => now()->subMinutes(20)]);

        $this->artisan('deployments:reconcile', ['--stale-minutes' => 5, '--queued-minutes' => 10])->assertSuccessful();

        Queue::assertPushed(ExecuteDeployment::class, fn (ExecuteDeployment $job): bool => $job->deploymentId === $deployment->id);
    }

    public function test_stale_provider_deployments_are_recovered_or_failed(): void
    {
        $recoveredApplication = Application::factory()->create(['status' => 'CREATED']);
        $recoveredApplication->environmentVariables()->create(['key' => 'RECOVERY_TOKEN', 'encrypted_value' => 'recovery-secret', 'is_secret' => true]);
        $recovered = Deployment::factory()->create([
            'application_id' => $recoveredApplication->id,
            'status' => 'DEPLOYING',
            'provider_deployment_id' => 'local-recovered',
            'build_logs' => 'provider finished recovery-secret',
            'updated_at' => now()->subMinutes(40),
        ]);
        $missingApplication = Application::factory()->create(['status' => 'CREATED']);
        $missing = Deployment::factory()->create([
            'application_id' => $missingApplication->id,
            'status' => 'BUILDING',
            'updated_at' => now()->subMinutes(40),
        ]);

        $this->artisan('deployments:reconcile', ['--stale-minutes' => 35, '--queued-minutes' => 10])->assertSuccessful();

        $this->assertSame('RUNNING', $recovered->fresh()->status);
        $this->assertSame('RUNNING', $recoveredApplication->fresh()->status);
        $this->assertSame('provider finished [REDACTED]', $recovered->fresh()->build_logs);
        $this->assertSame('FAILED', $missing->fresh()->status);
        $this->assertSame('DEPLOYMENT_RECOVERY_NOT_FOUND', $missing->fresh()->error_code);
        $this->assertSame('FAILED', $missingApplication->fresh()->status);
    }
}
