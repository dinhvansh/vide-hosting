<?php

namespace Tests\Feature\Api;

use App\Models\Application;
use App\Models\AuditLog;
use App\Models\Deployment;
use App\Models\User;
use App\Services\AuthTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_overview_and_suspend_user(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN', 'status' => 'ACTIVE']);
        $token = app(AuthTokenService::class)->create($admin, 'Test')['plain_text_token'];
        $user = User::factory()->create();
        $application = Application::factory()->for($user)->create(['status' => 'RUNNING']);
        Deployment::factory()->for($application)->create(['status' => 'FAILED', 'error_code' => 'BUILD_FAILED', 'error_message' => 'Build command failed.', 'finished_at' => now()]);

        $this->withToken($token)->getJson('/api/v1/admin/system/overview')->assertOk()
            ->assertJsonPath('data.users', 2)
            ->assertJsonPath('data.host.available', true)
            ->assertJsonPath('data.recent_failures.0.error_code', 'BUILD_FAILED')
            ->assertJsonPath('data.top_consumers.0.application.id', $application->id);
        $this->withToken($token)->postJson('/api/v1/admin/users/'.$user->id.'/suspend')->assertOk()->assertJsonPath('data.status', 'SUSPENDED');
        $this->assertDatabaseHas('audit_logs', ['actor_id' => $admin->id, 'actor_type' => 'ADMIN', 'action' => 'admin.user_suspended']);
    }

    public function test_normal_user_cannot_access_admin_routes(): void
    {
        $user = User::factory()->create();
        $token = app(AuthTokenService::class)->create($user, 'Test')['plain_text_token'];
        $this->withToken($token)->getJson('/api/v1/admin/users')->assertForbidden();
    }

    public function test_admin_route_returns_403_when_admin_is_suspended(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN', 'status' => 'SUSPENDED']);
        $token = app(AuthTokenService::class)->create($admin, 'Test')['plain_text_token'];

        $response = $this->withToken($token)->getJson('/api/v1/admin/system/overview');

        $response->assertForbidden()->assertJsonPath('error.code', 'ACCOUNT_SUSPENDED');
    }

    public function test_admin_lists_paginated_users_and_build_queue(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN', 'status' => 'ACTIVE']);
        $token = app(AuthTokenService::class)->create($admin, 'Test')['plain_text_token'];
        $user = User::factory()->create();
        Application::factory()->for($user)->create(['memory_limit_mb' => 768]);
        User::factory()->count(2)->create();

        $this->withToken($token)->getJson('/api/v1/admin/users?search='.$user->email)->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.applications_count', 1)
            ->assertJsonPath('data.0.applications_memory_limit_mb', 768);
        $this->withToken($token)->getJson('/api/v1/admin/system/build-queue')->assertOk()->assertJsonPath('meta.total', 0);
    }

    public function test_admin_overview_tracks_open_beta_product_metrics(): void
    {
        $this->travelTo('2026-09-01 12:00:00');
        $admin = User::factory()->create(['role' => 'ADMIN', 'status' => 'ACTIVE']);
        $token = app(AuthTokenService::class)->create($admin, 'Test')['plain_text_token'];
        $user = User::factory()->create();
        $repeatApp = Application::factory()->for($user)->create(['status' => 'RUNNING', 'created_at' => now()->subDays(10)]);
        $failedApp = Application::factory()->for($user)->create(['status' => 'FAILED']);
        Deployment::factory()->for($repeatApp)->create(['status' => 'RUNNING', 'created_at' => now()->subDays(9), 'finished_at' => now()->subDays(9)->addSeconds(120)]);
        Deployment::factory()->for($repeatApp)->create(['status' => 'RUNNING', 'created_at' => now()->subDays(8), 'finished_at' => now()->subDays(8)->addSeconds(60)]);
        Deployment::factory()->for($failedApp)->create(['status' => 'FAILED', 'created_at' => now()->subDay(), 'finished_at' => now()->subDay()->addSeconds(30)]);
        AuditLog::factory()->create(['action' => 'application.restarted']);

        $response = $this->withToken($token)->getJson('/api/v1/admin/system/overview');

        $response->assertOk()
            ->assertJsonPath('data.product_metrics.registrations', 2)
            ->assertJsonPath('data.product_metrics.verified_users', 2)
            ->assertJsonPath('data.product_metrics.application_creations', 2)
            ->assertJsonPath('data.product_metrics.successful_first_deployments', 1)
            ->assertJsonPath('data.product_metrics.deployment_success_rate_percent', 66.7)
            ->assertJsonPath('data.product_metrics.median_time_to_first_live_seconds', 120)
            ->assertJsonPath('data.product_metrics.repeat_deployment_rate_percent', 50)
            ->assertJsonPath('data.product_metrics.active_apps_after_7_days', 1)
            ->assertJsonPath('data.product_metrics.restart_actions', 1);
    }

    public function test_admin_overview_ignores_failures_for_deleted_applications(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN', 'status' => 'ACTIVE']);
        $token = app(AuthTokenService::class)->create($admin, 'Test')['plain_text_token'];
        $deletedApplication = Application::factory()->create();
        Deployment::factory()->for($deletedApplication)->create(['status' => 'FAILED', 'finished_at' => now()]);
        $deletedApplication->delete();

        $response = $this->withToken($token)->getJson('/api/v1/admin/system/overview');

        $response->assertOk()->assertJsonCount(0, 'data.recent_failures');
    }
}
