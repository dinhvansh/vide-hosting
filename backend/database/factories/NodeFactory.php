<?php

namespace Database\Factories;

use App\Enums\NodeStatus;
use App\Models\Node;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Node> */
class NodeFactory extends Factory
{
    protected $model = Node::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $code = strtoupper(fake()->unique()->bothify('VPS-##??'));

        return [
            'name' => $code,
            'code' => $code,
            'provider' => 'FAKE',
            'provider_server_id' => null,
            'region' => 'local',
            'status' => NodeStatus::Active,
            'cpu_total' => 8,
            'memory_total_mb' => 16384,
            'disk_total_mb' => 102400,
            'cpu_reserved' => 0,
            'memory_reserved_mb' => 0,
            'disk_reserved_mb' => 0,
        ];
    }
}
