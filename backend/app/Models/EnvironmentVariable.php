<?php

namespace App\Models;

use Database\Factories\EnvironmentVariableFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnvironmentVariable extends Model
{
    /** @use HasFactory<EnvironmentVariableFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['application_id', 'key', 'encrypted_value', 'is_secret'];

    protected $hidden = ['encrypted_value'];

    protected function casts(): array
    {
        return ['encrypted_value' => 'encrypted', 'is_secret' => 'boolean'];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}
