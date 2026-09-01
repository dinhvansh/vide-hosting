<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_seeding_is_idempotent_and_does_not_require_factories(): void
    {
        config(['services.admin_seed' => ['name' => 'Production Admin', 'email' => 'owner@example.test', 'password' => 'strong-password']]);

        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('users', 1);
        $admin = User::sole();
        $this->assertSame('SUPER_ADMIN', $admin->role);
        $this->assertSame('ACTIVE', $admin->status);
        $this->assertTrue(Hash::check('strong-password', $admin->password));
    }
}
