<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_liveness_is_public_and_minimal(): void
    {
        $this->getJson('/api/v1/health/live')->assertOk()->assertJsonPath('data.status', 'ok');
    }

    public function test_readiness_checks_required_dependencies(): void
    {
        $this->getJson('/api/v1/health/ready')->assertOk()
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.checks.database', true)
            ->assertJsonPath('data.checks.cache', true)
            ->assertJsonPath('data.checks.provider', true);
    }
}
