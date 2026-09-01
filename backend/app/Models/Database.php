<?php

namespace App\Models;

use Database\Factories\DatabaseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Database extends Model
{
    /** @use HasFactory<DatabaseFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['application_id', 'type', 'database_name', 'database_user', 'encrypted_password', 'host', 'port', 'provider_database_id', 'status'];

    protected $hidden = ['encrypted_password', 'provider_database_id'];

    protected function casts(): array
    {
        return ['encrypted_password' => 'encrypted', 'port' => 'integer'];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}
