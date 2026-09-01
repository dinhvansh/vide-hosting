<?php

namespace Tests\Feature\Console;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProductionPreflightTest extends TestCase
{
    public function test_valid_production_configuration_passes(): void
    {
        $this->configureValidProduction();

        $this->artisan('production:preflight')
            ->expectsOutputToContain('Production preflight passed.')
            ->assertSuccessful();
    }

    #[DataProvider('unsafeProductionConfigurations')]
    public function test_unsafe_production_configuration_fails(string $key, mixed $value, string $message): void
    {
        $this->configureValidProduction();
        config([$key => $value]);

        $this->artisan('production:preflight')
            ->expectsOutputToContain($message)
            ->assertFailed();
    }

    /** @return array<string, array{string, mixed, string}> */
    public static function unsafeProductionConfigurations(): array
    {
        return [
            'debug mode' => ['app.debug', true, 'APP_DEBUG must be false.'],
            'insecure public URL' => ['app.url', 'http://vive.example.com', 'APP_URL must be a valid HTTPS URL.'],
            'fake provider' => ['services.deployment_provider', 'fake', 'DEPLOYMENT_PROVIDER must be dokploy.'],
            'placeholder Dokploy token' => ['services.dokploy.token', 'example-dokploy-token', 'DOKPLOY_TOKEN must be set to a non-placeholder secret.'],
            'weak database password' => ['database.connections.pgsql.password', 'short', 'DB_PASSWORD must be set to a non-placeholder secret.'],
            'unprotected Redis' => ['database.redis.default.password', null, 'REDIS_PASSWORD must be set to a non-placeholder secret.'],
            'log mailer' => ['mail.default', 'log', 'MAIL_MAILER must use a supported delivery transport.'],
            'Postmark without API key' => ['mail.default', 'postmark', 'POSTMARK_API_KEY must be set to a non-placeholder secret.'],
            'weak admin password' => ['services.admin_seed.password', 'ChangeMe123!', 'ADMIN_PASSWORD must be set to a non-placeholder secret.'],
        ];
    }

    private function configureValidProduction(): void
    {
        config([
            'app.env' => 'production',
            'app.debug' => false,
            'app.key' => 'base64:'.base64_encode(str_repeat('k', 32)),
            'app.url' => 'https://vive.example.com',
            'services.frontend_url' => 'https://vive.example.com',
            'services.deployment_provider' => 'dokploy',
            'services.platform_domain' => 'apps.example.com',
            'services.dokploy.url' => 'http://dokploy.internal:3000',
            'services.dokploy.token' => 'real-dokploy-token-value-123',
            'services.admin_seed.email' => 'admin@vive.example.com',
            'services.admin_seed.password' => 'StrongAdminPassword!2026',
            'database.default' => 'pgsql',
            'database.connections.pgsql.password' => 'strong-database-password',
            'database.redis.default.password' => 'strong-redis-password',
            'cache.default' => 'redis',
            'queue.default' => 'redis',
            'session.driver' => 'redis',
            'logging.default' => 'stderr',
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.example.com',
            'mail.mailers.smtp.username' => 'vive-smtp-user',
            'mail.mailers.smtp.password' => 'strong-smtp-password',
            'mail.from.address' => 'no-reply@vive.example.com',
        ]);
    }
}
