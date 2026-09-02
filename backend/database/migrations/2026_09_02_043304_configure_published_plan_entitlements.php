<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $plans = [
            'STARTER' => ['max_apps' => 3, 'max_memory_mb_per_app' => 1024, 'max_cpu_per_app' => 1, 'max_disk_mb_per_app' => 10240, 'max_build_concurrency' => 1],
            'PRO' => ['max_apps' => 10, 'max_memory_mb_per_app' => 2048, 'max_cpu_per_app' => 2, 'max_disk_mb_per_app' => 20480, 'max_build_concurrency' => 3],
            'BUSINESS' => ['max_apps' => 30, 'max_memory_mb_per_app' => 4096, 'max_cpu_per_app' => 4, 'max_disk_mb_per_app' => 51200, 'max_build_concurrency' => 5],
        ];
        foreach ($plans as $code => $entitlements) {
            DB::table('plans')->where('code', $code)->update([...$entitlements, 'is_published' => true, 'updated_at' => now()]);
        }
        DB::table('subscriptions')->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->select('subscriptions.user_id', 'subscriptions.extra_app_slots', 'plans.max_apps', 'plans.max_memory_mb_per_app', 'plans.max_cpu_per_app', 'plans.max_disk_mb_per_app', 'plans.max_build_concurrency')
            ->orderBy('subscriptions.user_id')->each(function (object $row): void {
                DB::table('quotas')->where('user_id', $row->user_id)->update([
                    'max_apps' => $row->max_apps + $row->extra_app_slots,
                    'max_memory_mb_per_app' => $row->max_memory_mb_per_app,
                    'max_cpu_per_app' => $row->max_cpu_per_app,
                    'max_disk_mb_per_app' => $row->max_disk_mb_per_app,
                    'max_build_concurrency' => $row->max_build_concurrency,
                    'updated_at' => now(),
                ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('plans')->whereIn('code', ['STARTER', 'PRO', 'BUSINESS'])->update([
            'max_apps' => 1, 'max_memory_mb_per_app' => 512, 'max_cpu_per_app' => 0.5,
            'max_disk_mb_per_app' => 2048, 'max_build_concurrency' => 1, 'is_published' => false, 'updated_at' => now(),
        ]);
    }
};
