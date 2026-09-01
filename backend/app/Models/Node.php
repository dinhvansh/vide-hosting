<?php

namespace App\Models;

use App\Enums\NodeStatus;
use Database\Factories\NodeFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Node extends Model
{
    /** @use HasFactory<NodeFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'name', 'code', 'provider', 'provider_server_id', 'host', 'region', 'status',
        'cpu_total', 'memory_total_mb', 'disk_total_mb', 'cpu_reserved',
        'memory_reserved_mb', 'disk_reserved_mb', 'cpu_usage_percent',
        'memory_usage_mb', 'disk_usage_mb', 'last_heartbeat_at', 'metadata_json',
    ];

    protected $hidden = ['provider_server_id', 'host', 'metadata_json'];

    protected function casts(): array
    {
        return [
            'status' => NodeStatus::class,
            'cpu_total' => 'float',
            'cpu_reserved' => 'float',
            'cpu_usage_percent' => 'float',
            'memory_total_mb' => 'integer',
            'disk_total_mb' => 'integer',
            'memory_reserved_mb' => 'integer',
            'disk_reserved_mb' => 'integer',
            'memory_usage_mb' => 'integer',
            'disk_usage_mb' => 'integer',
            'last_heartbeat_at' => 'datetime',
            'metadata_json' => 'array',
        ];
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }
}
