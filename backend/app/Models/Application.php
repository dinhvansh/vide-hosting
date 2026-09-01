<?php

namespace App\Models;

use Database\Factories\ApplicationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Application extends Model
{
    /** @use HasFactory<ApplicationFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = ['user_id', 'node_id', 'name', 'slug', 'repository_url', 'branch', 'framework', 'status', 'provider', 'provider_application_id', 'cpu_limit', 'memory_limit_mb', 'disk_limit_mb'];

    protected function casts(): array
    {
        return ['cpu_limit' => 'decimal:2'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }

    public function deployments(): HasMany
    {
        return $this->hasMany(Deployment::class);
    }

    public function environmentVariables(): HasMany
    {
        return $this->hasMany(EnvironmentVariable::class);
    }

    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class);
    }

    public function databases(): HasMany
    {
        return $this->hasMany(Database::class);
    }

    public function providerResources(): HasMany
    {
        return $this->hasMany(ProviderResource::class);
    }
}
