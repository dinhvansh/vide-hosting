<?php

namespace App\Models;

use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $fillable = ['actor_type', 'actor_id', 'action', 'resource_type', 'resource_id', 'request_id', 'ip_address', 'metadata_json', 'created_at'];

    protected static function booted(): void
    {
        static::updating(fn (): bool => false);
        static::deleting(fn (): bool => false);
    }

    protected function casts(): array
    {
        return ['metadata_json' => 'array', 'created_at' => 'datetime'];
    }
}
