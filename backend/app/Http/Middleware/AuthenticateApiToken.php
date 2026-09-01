<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $plainTextToken = $request->bearerToken();
        $accessToken = $plainTextToken ? ApiToken::with('user')->where('token_hash', hash('sha256', $plainTextToken))->first() : null;
        $user = $accessToken?->user;

        if (! $user || ! $accessToken->isUsable()) {
            return response()->json(['error' => ['code' => 'UNAUTHENTICATED', 'message' => 'Authentication is required.', 'details' => (object) []], 'request_id' => $request->attributes->get('request_id')], 401);
        }

        auth()->setUser($user);
        $request->setUserResolver(fn () => $user);
        $request->attributes->set('access_token', $accessToken);
        if ($accessToken->last_used_at === null || $accessToken->last_used_at->lt(now()->subMinutes(5))) {
            $accessToken->forceFill(['last_used_at' => now()])->save();
        }

        return $next($request);
    }
}
