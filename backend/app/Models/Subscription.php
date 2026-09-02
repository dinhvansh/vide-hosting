<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    use HasUuids;

    protected $fillable = ['user_id', 'plan_id', 'status', 'billing_cycle', 'extra_app_slots', 'starts_at', 'ends_at', 'grace_ends_at', 'reminded_7d_at', 'reminded_3d_at', 'reminded_1d_at', 'expired_notified_at'];

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
        return [
            'starts_at' => 'datetime', 'ends_at' => 'datetime', 'grace_ends_at' => 'datetime',
            'reminded_7d_at' => 'datetime', 'reminded_3d_at' => 'datetime', 'reminded_1d_at' => 'datetime',
            'expired_notified_at' => 'datetime', 'extra_app_slots' => 'integer',
        ];
    }
}
