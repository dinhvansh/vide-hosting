<?php

namespace App\Models;

use Database\Factories\DomainFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Domain extends Model
{
    /** @use HasFactory<DomainFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['application_id', 'domain', 'type', 'status', 'ssl_status'];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}
