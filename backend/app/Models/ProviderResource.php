<?php

namespace App\Models;

use Database\Factories\ProviderResourceFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderResource extends Model
{
    /** @use HasFactory<ProviderResourceFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['application_id', 'provider', 'resource_type', 'local_reference', 'provider_resource_id', 'metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}
