<?php

namespace App\Providers;

use App\Contracts\DeploymentProvider;
use Illuminate\Support\ServiceProvider;

class DeploymentServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(DeploymentProvider::class, function () {
            return config('services.deployment_provider') === 'dokploy' ? app(DokployProvider::class) : app(FakeDeploymentProvider::class);
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
