<?php

namespace Tests\Feature\Api;

use App\Enums\NodeStatus;
use App\Models\Application;
use App\Models\Node;
use App\Models\User;
use App\Services\AuthTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NodeSchedulingTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_application_is_assigned_and_reserved_before_provider_creation(): void
    {
        [$user, $token] = $this->userWithToken();
        $node = Node::where('code', 'HOME-01')->firstOrFail();

        $response = $this->withToken($token)->postJson('/api/v1/apps', [
            'name' => 'Scheduled app',
            'repository_url' => 'https://github.com/example/scheduled-app',
        ]);

        $response->assertCreated();
        $application = Application::findOrFail($response->json('data.id'));
        $this->assertSame($node->id, $application->node_id);
        $this->assertSame(0.5, $node->fresh()->cpu_reserved);
        $this->assertSame(512, $node->fresh()->memory_reserved_mb);
        $this->assertSame(2048, $node->fresh()->disk_reserved_mb);
        $this->assertDatabaseHas('audit_logs', ['actor_id' => $user->id, 'action' => 'APP_NODE_ASSIGN', 'resource_id' => $application->id]);
    }

    public function test_draining_node_receives_no_new_apps_but_existing_app_can_redeploy(): void
    {
        [$user, $token] = $this->userWithToken();
        $draining = Node::where('code', 'HOME-01')->firstOrFail();
        $draining->update(['status' => NodeStatus::Draining]);
        $active = Node::factory()->create(['code' => 'VPS-02', 'status' => NodeStatus::Active]);
        $existing = Application::factory()->for($user)->create(['node_id' => $draining->id]);

        $this->withToken($token)->postJson('/api/v1/apps/'.$existing->id.'/deployments', ['branch' => 'main'])->assertAccepted();
        $user->quota()->updateOrCreate([], ['max_apps' => 2]);
        $created = $this->withToken($token)->postJson('/api/v1/apps', [
            'name' => 'New placement',
            'repository_url' => 'https://github.com/example/new-placement',
        ])->assertCreated();

        $this->assertSame($active->id, Application::findOrFail($created->json('data.id'))->node_id);
    }

    public function test_no_eligible_or_sufficient_node_returns_stable_no_capacity_error(): void
    {
        [, $token] = $this->userWithToken();
        Node::query()->update(['status' => NodeStatus::Draining->value]);

        $response = $this->withToken($token)->postJson('/api/v1/apps', [
            'name' => 'No capacity',
            'repository_url' => 'https://github.com/example/no-capacity',
        ]);

        $response->assertConflict()->assertJsonPath('error.code', 'NO_CAPACITY');
        $this->assertDatabaseCount('applications', 0);
    }

    public function test_reserved_cpu_memory_and_disk_all_protect_capacity(): void
    {
        [, $token] = $this->userWithToken();
        $node = Node::where('code', 'HOME-01')->firstOrFail();
        $node->update([
            'cpu_total' => 1.4,
            'memory_total_mb' => 2500,
            'disk_total_mb' => 12000,
            'cpu_reserved' => 0,
            'memory_reserved_mb' => 0,
            'disk_reserved_mb' => 0,
        ]);

        $this->withToken($token)->postJson('/api/v1/apps', [
            'name' => 'CPU blocked',
            'repository_url' => 'https://github.com/example/cpu-blocked',
        ])->assertConflict()->assertJsonPath('error.code', 'NO_CAPACITY');

        $node->update(['cpu_total' => 8, 'memory_total_mb' => 2400]);
        $this->withToken($token)->postJson('/api/v1/apps', [
            'name' => 'RAM blocked',
            'repository_url' => 'https://github.com/example/ram-blocked',
        ])->assertConflict()->assertJsonPath('error.code', 'NO_CAPACITY');

        $node->update(['memory_total_mb' => 16384, 'disk_total_mb' => 12000]);
        $this->withToken($token)->postJson('/api/v1/apps', [
            'name' => 'Disk blocked',
            'repository_url' => 'https://github.com/example/disk-blocked',
        ])->assertConflict()->assertJsonPath('error.code', 'NO_CAPACITY');
    }

    public function test_admin_manages_nodes_and_normal_user_cannot_access_infrastructure(): void
    {
        [$user, $userToken] = $this->userWithToken();
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $adminToken = app(AuthTokenService::class)->create($admin, 'Admin')['plain_text_token'];

        $this->withToken($userToken)->getJson('/api/v1/admin/nodes')->assertForbidden();
        $created = $this->withToken($adminToken)->postJson('/api/v1/admin/nodes', [
            'name' => 'VPS Singapore',
            'code' => 'VPS-SG-01',
            'provider' => 'DOKPLOY',
            'provider_server_id' => 'internal-server-id',
            'host' => '10.0.0.10',
            'region' => 'sg',
            'cpu_total' => 8,
            'memory_total_mb' => 16384,
            'disk_total_mb' => 102400,
        ])->assertCreated();
        $nodeId = $created->json('data.id');
        $this->assertStringNotContainsString('internal-server-id', $created->getContent());
        $this->assertStringNotContainsString('10.0.0.10', $created->getContent());

        $this->withToken($adminToken)->postJson('/api/v1/admin/nodes/'.$nodeId.'/drain')->assertOk()->assertJsonPath('data.status', 'DRAINING');
        $this->withToken($adminToken)->postJson('/api/v1/admin/nodes/'.$nodeId.'/maintenance')->assertOk()->assertJsonPath('data.status', 'MAINTENANCE');
        $this->withToken($adminToken)->postJson('/api/v1/admin/nodes/'.$nodeId.'/disable')->assertOk()->assertJsonPath('data.status', 'DISABLED');
        $this->withToken($adminToken)->postJson('/api/v1/admin/nodes/'.$nodeId.'/activate')->assertOk()->assertJsonPath('data.status', 'ACTIVE');
        $this->assertDatabaseHas('audit_logs', ['actor_id' => $admin->id, 'action' => 'NODE_DRAIN']);
        $this->assertSame($user->id, $user->fresh()->id);
    }

    public function test_user_application_api_does_not_leak_node_or_provider_metadata(): void
    {
        [, $token] = $this->userWithToken();
        $node = Node::where('code', 'HOME-01')->firstOrFail();
        $node->update(['provider_server_id' => 'secret-server-id', 'host' => '10.10.10.10']);

        $response = $this->withToken($token)->postJson('/api/v1/apps', [
            'name' => 'Private placement',
            'repository_url' => 'https://github.com/example/private-placement',
        ])->assertCreated();

        $payload = $response->getContent();
        $this->assertStringNotContainsString('node_id', $payload);
        $this->assertStringNotContainsString('secret-server-id', $payload);
        $this->assertStringNotContainsString('10.10.10.10', $payload);
    }

    /** @return array{User, string} */
    private function userWithToken(): array
    {
        $user = User::factory()->create();
        $token = app(AuthTokenService::class)->create($user, 'Test')['plain_text_token'];

        return [$user, $token];
    }
}
