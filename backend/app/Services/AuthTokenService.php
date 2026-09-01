<?php

namespace App\Services;

use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Support\Str;

class AuthTokenService
{
    /**
     * @param  array<int, string>  $abilities
     * @return array{access_token: ApiToken, plain_text_token: string}
     */
    public function create(User $user, string $name, string $actorType = 'USER', array $abilities = ['*'], ?int $ttlMinutes = null): array
    {
        $plainTextToken = Str::random(80);
        $tokenName = Str::limit(trim($name), 100, '');
        $accessToken = $user->apiTokens()->create([
            'name' => $tokenName !== '' ? $tokenName : 'API token',
            'token_hash' => hash('sha256', $plainTextToken),
            'actor_type' => $actorType,
            'abilities' => $abilities,
            'expires_at' => now()->addMinutes($ttlMinutes ?? (int) config('services.api_token_ttl_minutes', 43200)),
        ]);

        return ['access_token' => $accessToken, 'plain_text_token' => $plainTextToken];
    }
}
