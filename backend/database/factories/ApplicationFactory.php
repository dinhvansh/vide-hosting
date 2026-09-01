<?php

namespace Database\Factories;

use App\Models\Application;
use App\Models\Node;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Application>
 */
class ApplicationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'node_id' => Node::factory(),
            'name' => fake()->words(2, true),
            'slug' => fake()->unique()->slug(2),
            'repository_url' => 'https://github.com/example/'.fake()->unique()->slug(),
            'branch' => 'main',
            'framework' => 'auto',
            'status' => 'CREATED',
            'provider' => 'fake',
            'cpu_limit' => 0.5,
            'memory_limit_mb' => 512,
            'disk_limit_mb' => 2048,
        ];
    }
}
