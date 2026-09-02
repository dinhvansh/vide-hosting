<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasUuids;

    protected $fillable = ['code', 'name', 'monthly_price_vnd', 'max_apps', 'max_memory_mb_per_app', 'max_cpu_per_app', 'max_disk_mb_per_app', 'max_build_concurrency', 'is_default', 'is_published'];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    protected function casts(): array
    {
        return ['monthly_price_vnd' => 'integer', 'max_cpu_per_app' => 'float', 'is_default' => 'boolean', 'is_published' => 'boolean'];
    }
}
