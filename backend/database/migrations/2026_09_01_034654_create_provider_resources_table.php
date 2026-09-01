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
        Schema::create('provider_resources', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('application_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('resource_type');
            $table->string('local_reference');
            $table->string('provider_resource_id');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['application_id', 'resource_type', 'local_reference'], 'provider_resources_local_unique');
            $table->index(['provider', 'resource_type', 'provider_resource_id'], 'provider_resources_provider_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('provider_resources');
    }
};
