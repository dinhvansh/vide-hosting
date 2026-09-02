<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'status'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmailContract
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUuids, Notifiable;

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function apiTokens(): HasMany
    {
        return $this->hasMany(ApiToken::class);
    }

    public function quota(): HasOne
    {
        return $this->hasOne(Quota::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    public function paymentOrders(): HasMany
    {
        return $this->hasMany(PaymentOrder::class);
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['ADMIN', 'SUPER_ADMIN'], true);
    }

    public function isOperational(): bool
    {
        return in_array($this->status, ['ACTIVE', 'BETA'], true);
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify((new VerifyEmailNotification)->onConnection('redis-notifications')->onQueue('notifications'));
    }

    /** @param string $token */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify((new ResetPasswordNotification($token))->onConnection('redis-notifications')->onQueue('notifications'));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
