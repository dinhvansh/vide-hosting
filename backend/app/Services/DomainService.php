<?php

namespace App\Services;

use App\Contracts\DeploymentProvider;
use App\Models\Application;
use App\Models\Domain;
use Illuminate\Validation\ValidationException;

class DomainService
{
    public function __construct(private DeploymentProvider $provider) {}

    public function createCustom(Application $application, string $domainName): Domain
    {
        if (! config('services.custom_domains_enabled')) {
            throw ValidationException::withMessages(['domain' => ['Custom domains are not enabled for this environment.']]);
        }
        $result = $this->provider->addDomain($application, $domainName);

        return $application->domains()->create(['domain' => $domainName, 'type' => 'CUSTOM', 'status' => $result['status'], 'ssl_status' => $result['ssl_status']]);
    }

    public function delete(Application $application, Domain $domain): void
    {
        if ($domain->type === 'PLATFORM_SUBDOMAIN') {
            throw ValidationException::withMessages(['domain' => ['The platform domain cannot be removed.']]);
        }
        $this->provider->removeDomain($application, $domain->domain);
        $domain->delete();
    }
}
