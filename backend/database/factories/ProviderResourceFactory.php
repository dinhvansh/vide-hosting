<?php

namespace Database\Factories;

use App\Models\Application;
use App\Models\ProviderResource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProviderResource>
 */
class ProviderResourceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'application_id' => Application::factory(),
            'provider' => 'dokploy',
            'resource_type' => 'application',
            'local_reference' => 'primary',
            'provider_resource_id' => fake()->uuid(),
            'metadata' => [],
        ];
    }
}
