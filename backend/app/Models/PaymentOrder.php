<?php

namespace App\Models;

use Database\Factories\PaymentOrderFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentOrder extends Model
{
    /** @use HasFactory<PaymentOrderFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['user_id', 'plan_id', 'invoice_number', 'type', 'duration_months', 'quantity', 'amount_vnd', 'status', 'provider', 'provider_order_id', 'provider_transaction_id', 'provider_payload', 'approved_at', 'expires_at'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    protected function casts(): array
    {
        return ['amount_vnd' => 'integer', 'provider_payload' => 'array', 'approved_at' => 'datetime', 'expires_at' => 'datetime'];
    }
}
