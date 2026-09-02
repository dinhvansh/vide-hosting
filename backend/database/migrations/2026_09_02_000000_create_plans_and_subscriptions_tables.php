<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->string('name');
            $table->unsignedBigInteger('monthly_price_vnd')->default(0);
            $table->unsignedInteger('max_apps')->default(1);
            $table->unsignedInteger('max_memory_mb_per_app')->default(512);
            $table->decimal('max_cpu_per_app', 3, 2)->default(0.5);
            $table->unsignedInteger('max_disk_mb_per_app')->default(2048);
            $table->unsignedInteger('max_build_concurrency')->default(1);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_published')->default(false);
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignUuid('plan_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('TRIALING')->index();
            $table->string('billing_cycle')->default('MONTHLY');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable()->index();
            $table->timestamp('grace_ends_at')->nullable()->index();
            $table->timestamp('reminded_7d_at')->nullable();
            $table->timestamp('reminded_3d_at')->nullable();
            $table->timestamp('reminded_1d_at')->nullable();
            $table->timestamp('expired_notified_at')->nullable();
            $table->timestamps();
        });

        $now = now();
        $freePlanId = (string) Str::uuid();
        DB::table('plans')->insert([
            [
                'id' => $freePlanId, 'code' => 'FREE', 'name' => 'Open Beta', 'monthly_price_vnd' => 0,
                'max_apps' => 1, 'max_memory_mb_per_app' => 512, 'max_cpu_per_app' => 0.5,
                'max_disk_mb_per_app' => 2048, 'max_build_concurrency' => 1,
                'is_default' => true, 'is_published' => true, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'id' => (string) Str::uuid(), 'code' => 'STARTER', 'name' => 'Starter', 'monthly_price_vnd' => 79000,
                'max_apps' => 1, 'max_memory_mb_per_app' => 512, 'max_cpu_per_app' => 0.5,
                'max_disk_mb_per_app' => 2048, 'max_build_concurrency' => 1,
                'is_default' => false, 'is_published' => false, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'id' => (string) Str::uuid(), 'code' => 'PRO', 'name' => 'Pro', 'monthly_price_vnd' => 149000,
                'max_apps' => 1, 'max_memory_mb_per_app' => 512, 'max_cpu_per_app' => 0.5,
                'max_disk_mb_per_app' => 2048, 'max_build_concurrency' => 1,
                'is_default' => false, 'is_published' => false, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'id' => (string) Str::uuid(), 'code' => 'BUSINESS', 'name' => 'Business', 'monthly_price_vnd' => 299000,
                'max_apps' => 1, 'max_memory_mb_per_app' => 512, 'max_cpu_per_app' => 0.5,
                'max_disk_mb_per_app' => 2048, 'max_build_concurrency' => 1,
                'is_default' => false, 'is_published' => false, 'created_at' => $now, 'updated_at' => $now,
            ],
        ]);

        DB::table('users')->orderBy('id')->each(function (object $user) use ($freePlanId, $now): void {
            DB::table('subscriptions')->insert([
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'plan_id' => $freePlanId,
                'status' => 'TRIALING',
                'billing_cycle' => 'MONTHLY',
                'starts_at' => $now,
                'ends_at' => $now->copy()->addMonth(),
                'grace_ends_at' => $now->copy()->addMonth()->addDays(3),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plans');
    }
};
