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
        Schema::create('nodes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('provider')->default('FAKE');
            $table->string('provider_server_id')->nullable();
            $table->string('host')->nullable();
            $table->string('region')->nullable();
            $table->string('status')->default('ACTIVE')->index();
            $table->decimal('cpu_total', 8, 2);
            $table->unsignedBigInteger('memory_total_mb');
            $table->unsignedBigInteger('disk_total_mb');
            $table->decimal('cpu_reserved', 8, 2)->default(0);
            $table->unsignedBigInteger('memory_reserved_mb')->default(0);
            $table->unsignedBigInteger('disk_reserved_mb')->default(0);
            $table->decimal('cpu_usage_percent', 5, 2)->nullable();
            $table->unsignedBigInteger('memory_usage_mb')->nullable();
            $table->unsignedBigInteger('disk_usage_mb')->nullable();
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamps();
        });

        $nodeId = (string) Str::uuid();
        DB::table('nodes')->insert([
            'id' => $nodeId,
            'name' => config('nodes.default.name'),
            'code' => config('nodes.default.code'),
            'provider' => config('nodes.default.provider'),
            'provider_server_id' => config('nodes.default.provider_server_id'),
            'region' => config('nodes.default.region'),
            'status' => 'ACTIVE',
            'cpu_total' => config('nodes.default.cpu_total'),
            'memory_total_mb' => config('nodes.default.memory_total_mb'),
            'disk_total_mb' => config('nodes.default.disk_total_mb'),
            'cpu_reserved' => 0,
            'memory_reserved_mb' => 0,
            'disk_reserved_mb' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('applications', function (Blueprint $table) {
            $table->foreignUuid('node_id')->nullable()->after('user_id')->constrained('nodes')->restrictOnDelete();
        });

        DB::table('applications')->update(['node_id' => $nodeId]);
        $reserved = DB::table('applications')->whereNull('deleted_at')->selectRaw('COALESCE(SUM(cpu_limit), 0) AS cpu, COALESCE(SUM(memory_limit_mb), 0) AS memory, COALESCE(SUM(disk_limit_mb), 0) AS disk')->first();
        DB::table('nodes')->where('id', $nodeId)->update([
            'cpu_reserved' => $reserved->cpu,
            'memory_reserved_mb' => $reserved->memory,
            'disk_reserved_mb' => $reserved->disk,
        ]);

        Schema::table('applications', function (Blueprint $table) {
            $table->uuid('node_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('node_id');
        });
        Schema::dropIfExists('nodes');
    }
};
