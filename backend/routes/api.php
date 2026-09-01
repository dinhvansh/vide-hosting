<?php

use App\Http\Controllers\Api\V1\AdminController;
use App\Http\Controllers\Api\V1\ApplicationController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DatabaseController;
use App\Http\Controllers\Api\V1\DeploymentController;
use App\Http\Controllers\Api\V1\DomainController;
use App\Http\Controllers\Api\V1\EmailVerificationController;
use App\Http\Controllers\Api\V1\EnvironmentVariableController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\NodeController;
use App\Http\Controllers\Api\V1\PasswordController;
use App\Http\Controllers\Api\V1\TokenController;
use App\Http\Controllers\Api\V1\UsageController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health/live', [HealthController::class, 'live'])->middleware('throttle:health');
    Route::get('/health/ready', [HealthController::class, 'ready'])->middleware('throttle:health');
    Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:auth');
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:auth');
    Route::post('/auth/forgot-password', [PasswordController::class, 'forgot'])->middleware('throttle:auth');
    Route::post('/auth/reset-password', [PasswordController::class, 'reset'])->middleware('throttle:auth');
    Route::get('/auth/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])->middleware(['signed', 'throttle:auth'])->name('verification.verify');

    Route::middleware(['api.token', 'mcp.audit', 'throttle:api'])->group(function (): void {
        Route::get('/me', [AuthController::class, 'me'])->middleware('token.ability:account:read');
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/auth/email/verification-notification', [EmailVerificationController::class, 'send'])->middleware(['active', 'throttle:auth']);
        Route::get('/tokens', [TokenController::class, 'index'])->middleware('token.ability:tokens:manage');
        Route::post('/tokens/mcp', [TokenController::class, 'createMcp'])->middleware(['active', 'token.ability:tokens:manage']);
        Route::delete('/tokens/{token}', [TokenController::class, 'destroy'])->middleware('token.ability:tokens:manage');
        Route::get('/apps', [ApplicationController::class, 'index'])->middleware('token.ability:projects:read');
        Route::get('/apps/{app}', [ApplicationController::class, 'show'])->middleware('token.ability:projects:read');
        Route::get('/apps/{app}/deployments', [DeploymentController::class, 'index'])->middleware('token.ability:deployments:read');
        Route::get('/apps/{app}/deployments/{deployment}', [DeploymentController::class, 'show'])->middleware('token.ability:deployments:read');
        Route::get('/apps/{app}/deployments/{deployment}/logs', [DeploymentController::class, 'logs'])->middleware(['token.ability:deployments:read', 'throttle:logs']);
        Route::get('/apps/{app}/logs/runtime', [DeploymentController::class, 'runtimeLogs'])->middleware(['token.ability:deployments:read', 'throttle:logs']);
        Route::get('/apps/{app}/env', [EnvironmentVariableController::class, 'index'])->middleware('token.ability:env:read');
        Route::get('/deployments/{deployment}', [DeploymentController::class, 'showDirect'])->middleware('token.ability:deployments:read');
        Route::get('/deployments/{deployment}/logs', [DeploymentController::class, 'logsDirect'])->middleware(['token.ability:deployments:read', 'throttle:logs']);
        Route::get('/apps/{app}/domains', [DomainController::class, 'index'])->middleware('token.ability:domains:read');
        Route::get('/apps/{app}/databases', [DatabaseController::class, 'index'])->middleware('token.ability:databases:read');
        Route::get('/apps/{app}/usage', [UsageController::class, 'show'])->middleware('token.ability:usage:read');
        Route::get('/usage', [UsageController::class, 'index'])->middleware('token.ability:usage:read');

        Route::middleware('active')->group(function (): void {
            Route::post('/apps', [ApplicationController::class, 'store'])->middleware('token.ability:projects:create');
            Route::patch('/apps/{app}', [ApplicationController::class, 'update'])->middleware('token.ability:apps:write');
            Route::delete('/apps/{app}', [ApplicationController::class, 'destroy'])->middleware('token.ability:apps:delete');
            Route::post('/apps/{app}/deployments', [DeploymentController::class, 'store'])->middleware(['token.ability:deployments:create', 'throttle:deployment-create']);
            Route::post('/apps/{app}/restart', [DeploymentController::class, 'restart'])->middleware(['token.ability:apps:operate', 'throttle:app-operation']);
            Route::post('/apps/{app}/stop', [DeploymentController::class, 'stop'])->middleware(['token.ability:apps:operate', 'throttle:app-operation']);
            Route::post('/apps/{app}/env', [EnvironmentVariableController::class, 'store'])->middleware('token.ability:env:write');
            Route::patch('/apps/{app}/env/{key}', [EnvironmentVariableController::class, 'store'])->middleware('token.ability:env:write');
            Route::delete('/apps/{app}/env/{key}', [EnvironmentVariableController::class, 'destroy'])->middleware('token.ability:env:write');
            Route::post('/apps/{app}/domains', [DomainController::class, 'store'])->middleware('token.ability:domains:write');
            Route::delete('/apps/{app}/domains/{domain}', [DomainController::class, 'destroy'])->middleware('token.ability:domains:write');
            Route::post('/apps/{app}/databases', [DatabaseController::class, 'store'])->middleware('token.ability:databases:write');
            Route::delete('/apps/{app}/databases/{database}', [DatabaseController::class, 'destroy'])->middleware('token.ability:databases:write');
        });

        Route::prefix('admin')->middleware(['active', 'admin', 'token.ability:admin'])->group(function (): void {
            Route::get('/system/overview', [AdminController::class, 'overview']);
            Route::get('/system/build-queue', [AdminController::class, 'buildQueue']);
            Route::get('/nodes', [NodeController::class, 'index']);
            Route::post('/nodes', [NodeController::class, 'store']);
            Route::get('/nodes/{node}', [NodeController::class, 'show']);
            Route::patch('/nodes/{node}', [NodeController::class, 'update']);
            Route::post('/nodes/{node}/drain', [NodeController::class, 'drain']);
            Route::post('/nodes/{node}/activate', [NodeController::class, 'activate']);
            Route::post('/nodes/{node}/maintenance', [NodeController::class, 'maintenance']);
            Route::post('/nodes/{node}/disable', [NodeController::class, 'disable']);
            Route::get('/users', [AdminController::class, 'users']);
            Route::get('/users/{user}', [AdminController::class, 'user']);
            Route::get('/apps', [AdminController::class, 'apps']);
            Route::get('/apps/{app}', [AdminController::class, 'app']);
            Route::post('/users/{user}/suspend', [AdminController::class, 'suspend']);
            Route::post('/users/{user}/activate', [AdminController::class, 'activate']);
            Route::patch('/users/{user}/quota', [AdminController::class, 'quota']);
            Route::post('/apps/{app}/restart', [AdminController::class, 'restartApp'])->middleware('throttle:app-operation');
            Route::post('/apps/{app}/stop', [AdminController::class, 'stopApp'])->middleware('throttle:app-operation');
            Route::post('/apps/{app}/redeploy', [AdminController::class, 'redeployApp'])->middleware('throttle:deployment-create');
            Route::delete('/apps/{app}', [AdminController::class, 'deleteApp'])->middleware('throttle:app-operation');
        });
    });
});
