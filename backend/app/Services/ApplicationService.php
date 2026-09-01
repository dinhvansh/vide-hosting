<?php

namespace App\Services;

use App\Contracts\DeploymentProvider;
use App\Exceptions\PlatformException;
use App\Exceptions\ProviderException;
use App\Models\Application;
use App\Models\Domain;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ApplicationService
{
    public function __construct(private QuotaService $quotas, private NodeScheduler $scheduler, private DeploymentProvider $provider) {}

    /** @param array{name: string, repository_url: string, branch?: string, framework?: string} $data */
    public function create(User $user, array $data): Application
    {
        $quota = $this->quotas->assertCanCreateApplication($user);
        $slug = (Str::slug($data['name']) ?: 'app').'-'.Str::lower(Str::random(5));
        $application = DB::transaction(function () use ($user, $data, $slug, $quota): Application {
            $node = $this->scheduler->selectNodeForApplication(
                (float) $quota->max_cpu_per_app,
                $quota->max_memory_mb_per_app,
                $quota->max_disk_mb_per_app,
            );

            return Application::create(['user_id' => $user->id, 'node_id' => $node->id, 'name' => $data['name'], 'slug' => $slug, 'repository_url' => $data['repository_url'], 'branch' => $data['branch'] ?? 'main', 'framework' => $data['framework'] ?? 'auto', 'provider' => strtolower($node->provider), 'cpu_limit' => $quota->max_cpu_per_app, 'memory_limit_mb' => $quota->max_memory_mb_per_app, 'disk_limit_mb' => $quota->max_disk_mb_per_app]);
        }, 3);
        $node = $application->node()->firstOrFail();
        try {
            $result = $this->provider->createApplication($node, $application);
        } catch (\Throwable $exception) {
            $this->scheduler->releaseAndDeleteApplication($application, force: true);
            if ($exception instanceof PlatformException) {
                throw $exception;
            }
            throw new ProviderException(previous: $exception);
        }
        $application->update(['provider_application_id' => $result['provider_application_id']]);
        try {
            $this->initializeFrameworkEnvironment($application);
        } catch (\Throwable $exception) {
            try {
                $this->provider->delete($application);
            } catch (\Throwable) {
            }
            $this->scheduler->releaseAndDeleteApplication($application, force: true);
            if ($exception instanceof PlatformException) {
                throw $exception;
            }
            throw new ProviderException(previous: $exception);
        }
        Domain::create(['application_id' => $application->id, 'domain' => $result['domain'], 'status' => 'ACTIVE', 'ssl_status' => 'ACTIVE']);

        return $application->fresh(['domains', 'deployments']);
    }

    public function delete(Application $application): void
    {
        $this->provider->delete($application);
        $this->scheduler->releaseAndDeleteApplication($application);
    }

    private function initializeFrameworkEnvironment(Application $application): void
    {
        if ($application->framework === 'static') {
            return;
        }

        $variables = $application->framework === 'laravel'
            ? ['APP_KEY' => 'base64:'.base64_encode(random_bytes(32))]
            : ['PORT' => '3000'];
        $this->provider->setEnvironmentVariables($application, $variables);
        foreach ($variables as $key => $value) {
            $application->environmentVariables()->create([
                'key' => $key,
                'encrypted_value' => $value,
                'is_secret' => $key === 'APP_KEY',
            ]);
        }
    }

    /** @param array{name?: string, branch?: string, framework?: string} $data */
    public function update(Application $application, array $data): Application
    {
        $application->fill($data);
        if ($application->isDirty(['branch', 'framework'])) {
            $this->provider->updateApplication($application);
        }
        $application->save();

        return $application->fresh(['domains', 'deployments']);
    }
}
