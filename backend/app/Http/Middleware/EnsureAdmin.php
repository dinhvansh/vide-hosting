<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isAdmin() || $request->attributes->get('access_token')?->actor_type === 'MCP') {
            return response()->json(['error' => ['code' => 'FORBIDDEN', 'message' => 'Administrator access is required.', 'details' => (object) []], 'request_id' => $request->attributes->get('request_id')], 403);
        }

        return $next($request);
    }
}
