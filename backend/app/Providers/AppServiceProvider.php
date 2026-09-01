<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        VerifyEmail::createUrlUsing(function (mixed $notifiable): string {
            $apiUrl = URL::temporarySignedRoute('verification.verify', now()->addMinutes((int) config('auth.verification.expire', 60)), [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]);

            return rtrim((string) config('services.frontend_url'), '/').'/verify-email?'.http_build_query(['url' => $apiUrl]);
        });
        ResetPassword::createUrlUsing(fn (mixed $notifiable, string $token): string => rtrim((string) config('services.frontend_url'), '/').'/reset-password?'.http_build_query(['token' => $token, 'email' => $notifiable->getEmailForPasswordReset()]));

        RateLimiter::for('health', fn (Request $request): Limit => Limit::perMinute(60)->by('health:'.$request->ip()));
        RateLimiter::for('auth', fn (Request $request): Limit => Limit::perMinute(10)->by('auth:'.$request->ip()));
        RateLimiter::for('api', fn (Request $request): Limit => Limit::perMinute(120)->by('api:'.($request->user()?->getAuthIdentifier() ?? $request->ip())));
        RateLimiter::for('deployment-create', fn (Request $request): Limit => Limit::perMinute(5)->by('deployment-create:'.($request->user()?->getAuthIdentifier() ?? $request->ip())));
        RateLimiter::for('app-operation', fn (Request $request): Limit => Limit::perMinute(10)->by('app-operation:'.($request->user()?->getAuthIdentifier() ?? $request->ip())));
        RateLimiter::for('logs', fn (Request $request): Limit => Limit::perMinute(60)->by('logs:'.($request->user()?->getAuthIdentifier() ?? $request->ip())));
    }
}
