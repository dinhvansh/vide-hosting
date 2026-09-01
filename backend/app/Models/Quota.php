<?php

namespace App\Models;

use Database\Factories\QuotaFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Quota extends Model
{
    /** @use HasFactory<QuotaFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['user_id', 'max_apps', 'max_memory_mb_per_app', 'max_cpu_per_app', 'max_disk_mb_per_app', 'max_build_concurrency'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
