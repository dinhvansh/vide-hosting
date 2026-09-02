<?php

use App\Exceptions\PlatformException;
use App\Http\Middleware\AddRequestId;
use App\Http\Middleware\AuditMcpRequest;
use App\Http\Middleware\AuthenticateApiToken;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureSubscriptionActive;
use App\Http\Middleware\EnsureTokenAbility;
use App\Http\Middleware\EnsureUserActive;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'api.token' => AuthenticateApiToken::class,
            'mcp.audit' => AuditMcpRequest::class,
            'active' => EnsureUserActive::class,
            'subscription.active' => EnsureSubscriptionActive::class,
            'admin' => EnsureAdmin::class,
            'token.ability' => EnsureTokenAbility::class,
        ]);
        $middleware->prepend(AddRequestId::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }
            $quota = array_key_exists('quota', $exception->errors());

            return response()->json(['error' => ['code' => $quota ? 'QUOTA_EXCEEDED' : 'VALIDATION_FAILED', 'message' => $quota ? 'Application limit reached.' : 'The submitted data is invalid.', 'details' => $exception->errors()], 'request_id' => $request->attributes->get('request_id')], 422);
        });
        $exceptions->render(function (PlatformException $exception, Request $request) {
            return response()->json(['error' => ['code' => $exception->errorCode, 'message' => $exception->getMessage(), 'details' => $exception->details ?: (object) []], 'request_id' => $request->attributes->get('request_id')], $exception->httpStatus);
        });
        $exceptions->render(function (ModelNotFoundException $exception, Request $request) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => 'The requested resource was not found.', 'details' => (object) []], 'request_id' => $request->attributes->get('request_id')], 404);
        });
        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            $status = $exception->getStatusCode();
            $code = match ($status) {
                403 => 'FORBIDDEN', 404 => 'NOT_FOUND', 409 => 'CONFLICT', 429 => 'RATE_LIMITED', default => 'HTTP_ERROR'
            };
            $message = $exception->getMessage() ?: match ($status) {
                403 => 'This action is not allowed.', 404 => 'The requested resource was not found.', 409 => 'The request conflicts with the current resource state.', 429 => 'Too many requests. Please try again later.', default => 'The request could not be completed.'
            };

            return response()->json(['error' => ['code' => $code, 'message' => $message, 'details' => (object) []], 'request_id' => $request->attributes->get('request_id')], $status);
        });
        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json(['error' => ['code' => 'INTERNAL_ERROR', 'message' => 'An unexpected error occurred. Please try again.', 'details' => (object) []], 'request_id' => $request->attributes->get('request_id')], 500);
        });
    })->create();
