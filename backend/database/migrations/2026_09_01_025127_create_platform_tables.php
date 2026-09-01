<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('repository_url');
            $table->string('branch')->default('main');
            $table->string('framework')->default('auto');
            $table->string('status')->default('CREATED')->index();
            $table->string('provider')->default('fake');
            $table->string('provider_application_id')->nullable();
            $table->decimal('cpu_limit', 3, 2)->default(0.5);
            $table->unsignedInteger('memory_limit_mb')->default(512);
            $table->unsignedInteger('disk_limit_mb')->default(2048);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['user_id', 'slug']);
        });

        Schema::create('deployments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('application_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('QUEUED')->index();
            $table->string('branch');
            $table->string('commit_sha')->nullable();
            $table->string('provider_deployment_id')->nullable();
            $table->timestamp('build_started_at')->nullable();
            $table->timestamp('deploy_started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->longText('build_logs')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('environment_variables', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('application_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->text('encrypted_value');
            $table->boolean('is_secret')->default(true);
            $table->timestamps();
            $table->unique(['application_id', 'key']);
        });

        Schema::create('domains', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('application_id')->constrained()->cascadeOnDelete();
            $table->string('domain')->unique();
            $table->string('type')->default('PLATFORM_SUBDOMAIN');
            $table->string('status')->default('PENDING');
            $table->string('ssl_status')->default('PENDING');
            $table->timestamps();
        });

        Schema::create('quotas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('max_apps')->default(1);
            $table->unsignedInteger('max_memory_mb_per_app')->default(512);
            $table->decimal('max_cpu_per_app', 3, 2)->default(0.5);
            $table->unsignedInteger('max_disk_mb_per_app')->default(2048);
            $table->unsignedInteger('max_build_concurrency')->default(1);
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('actor_type');
            $table->uuid('actor_id')->nullable();
            $table->string('action')->index();
            $table->string('resource_type');
            $table->uuid('resource_id')->nullable();
            $table->uuid('request_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('quotas');
        Schema::dropIfExists('domains');
        Schema::dropIfExists('environment_variables');
        Schema::dropIfExists('deployments');
        Schema::dropIfExists('applications');
    }
};
