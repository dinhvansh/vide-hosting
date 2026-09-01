<?php

namespace Database\Seeders;

use App\Models\Node;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Node::firstOrCreate(
            ['code' => config('nodes.default.code')],
            [
                'name' => config('nodes.default.name'),
                'provider' => config('nodes.default.provider'),
                'provider_server_id' => config('nodes.default.provider_server_id'),
                'region' => config('nodes.default.region'),
                'status' => 'ACTIVE',
                'cpu_total' => config('nodes.default.cpu_total'),
                'memory_total_mb' => config('nodes.default.memory_total_mb'),
                'disk_total_mb' => config('nodes.default.disk_total_mb'),
            ],
        );

        User::firstOrCreate(
            ['email' => config('services.admin_seed.email')],
            [
                'name' => config('services.admin_seed.name'),
                'password' => Hash::make(config('services.admin_seed.password')),
                'role' => 'SUPER_ADMIN',
                'status' => 'ACTIVE',
            ],
        );
    }
}
