<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserActive
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isOperational()) {
            return response()->json(['error' => ['code' => 'ACCOUNT_SUSPENDED', 'message' => 'This account cannot perform operational actions.', 'details' => (object) []], 'request_id' => $request->attributes->get('request_id')], 403);
        }

        return $next($request);
    }
}
