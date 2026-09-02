<?php

namespace App\Http\Middleware;

use App\Services\SubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscriptionActive
{
    public function __construct(private SubscriptionService $subscriptions) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->subscriptions->assertCanProvision($request->user());

        return $next($request);
    }
}
