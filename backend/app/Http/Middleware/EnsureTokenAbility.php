<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTokenAbility
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        $accessToken = $request->attributes->get('access_token');
        if (! $accessToken instanceof ApiToken || ! $accessToken->can($ability)) {
            return response()->json(['error' => ['code' => 'TOKEN_ABILITY_REQUIRED', 'message' => 'This token cannot perform the requested action.', 'details' => ['ability' => $ability]], 'request_id' => $request->attributes->get('request_id')], 403);
        }

        return $next($request);
    }
}
