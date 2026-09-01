<?php

namespace App\Models;

use Database\Factories\DeploymentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Deployment extends Model
{
    /** @use HasFactory<DeploymentFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['application_id', 'status', 'branch', 'commit_sha', 'provider_deployment_id', 'build_started_at', 'deploy_started_at', 'finished_at', 'error_code', 'error_message', 'build_logs', 'created_by', 'idempotency_key'];

    protected function casts(): array
    {
        return ['build_started_at' => 'datetime', 'deploy_started_at' => 'datetime', 'finished_at' => 'datetime'];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}
