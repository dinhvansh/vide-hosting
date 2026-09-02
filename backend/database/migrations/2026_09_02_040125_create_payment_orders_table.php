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
        Schema::create('payment_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('plan_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('invoice_number')->unique();
            $table->string('type')->index();
            $table->unsignedInteger('duration_months')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('amount_vnd');
            $table->string('status')->default('PENDING')->index();
            $table->string('provider')->default('SEPAY');
            $table->string('provider_order_id')->nullable();
            $table->string('provider_transaction_id')->nullable();
            $table->json('provider_payload')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_orders');
    }
};
