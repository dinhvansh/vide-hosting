<?php

namespace App\Services;

class ProductionPreflight
{
    /** @return array<int, string> */
    public function errors(): array
    {
        $errors = [];
        $this->require(config('app.env') === 'production', 'APP_ENV must be production.', $errors);
        $this->require(config('app.debug') === false, 'APP_DEBUG must be false.', $errors);
        $this->require($this->validAppKey(config('app.key')), 'APP_KEY must be a unique base64-encoded 32-byte key.', $errors);
        $this->require($this->isHttpsUrl(config('app.url')), 'APP_URL must be a valid HTTPS URL.', $errors);
        $this->require($this->isHttpsUrl(config('services.frontend_url')), 'FRONTEND_URL must be a valid HTTPS URL.', $errors);
        $this->require(config('services.deployment_provider') === 'dokploy', 'DEPLOYMENT_PROVIDER must be dokploy.', $errors);
        $this->require($this->isHttpUrl(config('services.dokploy.url')), 'DOKPLOY_URL must be a valid HTTP or HTTPS URL.', $errors);
        $this->require($this->isStrongSecret(config('services.dokploy.token')), 'DOKPLOY_TOKEN must be set to a non-placeholder secret.', $errors);
        $this->require($this->validDomain(config('services.platform_domain')), 'PLATFORM_DOMAIN must be a valid domain.', $errors);
        $this->require(config('database.default') === 'pgsql', 'DB_CONNECTION must be pgsql.', $errors);
        $this->require($this->isStrongSecret(config('database.connections.pgsql.password')), 'DB_PASSWORD must be set to a non-placeholder secret.', $errors);
        $this->require(config('cache.default') === 'redis', 'CACHE_STORE must be redis.', $errors);
        $this->require(config('queue.default') === 'redis', 'QUEUE_CONNECTION must be redis.', $errors);
        $this->require(config('session.driver') === 'redis', 'SESSION_DRIVER must be redis.', $errors);
        $this->require($this->isStrongSecret(config('database.redis.default.password')), 'REDIS_PASSWORD must be set to a non-placeholder secret.', $errors);
        $this->require(config('logging.default') === 'stderr', 'LOG_CHANNEL must be stderr.', $errors);
        $this->validateAdmin($errors);
        $this->validateMail($errors);

        return $errors;
    }

    /** @param array<int, string> $errors */
    private function validateAdmin(array &$errors): void
    {
        $this->require(filter_var(config('services.admin_seed.email'), FILTER_VALIDATE_EMAIL) !== false, 'ADMIN_EMAIL must be valid.', $errors);
        $this->require($this->isStrongSecret(config('services.admin_seed.password')), 'ADMIN_PASSWORD must be set to a non-placeholder secret.', $errors);
    }

    /** @param array<int, string> $errors */
    private function validateMail(array &$errors): void
    {
        $mailer = config('mail.default');
        $supported = ['smtp', 'postmark', 'resend', 'ses', 'ses-v2', 'sendmail'];
        $this->require(is_string($mailer) && in_array($mailer, $supported, true), 'MAIL_MAILER must use a supported delivery transport.', $errors);
        $this->require(filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL) !== false, 'MAIL_FROM_ADDRESS must be valid.', $errors);
        match ($mailer) {
            'smtp' => $this->validateSmtp($errors),
            'postmark' => $this->require($this->isStrongSecret(config('services.postmark.key')), 'POSTMARK_API_KEY must be set to a non-placeholder secret.', $errors),
            'resend' => $this->require($this->isStrongSecret(config('services.resend.key')), 'RESEND_API_KEY must be set to a non-placeholder secret.', $errors),
            'ses', 'ses-v2' => $this->validateSes($errors),
            'sendmail' => $this->require($this->nonEmpty(config('mail.mailers.sendmail.path')), 'MAIL_SENDMAIL_PATH must be set.', $errors),
            default => null,
        };
    }

    /** @param array<int, string> $errors */
    private function validateSmtp(array &$errors): void
    {
        $this->require($this->nonEmpty(config('mail.mailers.smtp.host')), 'MAIL_HOST must be set for SMTP.', $errors);
        $this->require($this->nonEmpty(config('mail.mailers.smtp.username')), 'MAIL_USERNAME must be set for SMTP.', $errors);
        $this->require($this->isStrongSecret(config('mail.mailers.smtp.password')), 'MAIL_PASSWORD must be set to a non-placeholder secret.', $errors);
    }

    /** @param array<int, string> $errors */
    private function validateSes(array &$errors): void
    {
        $this->require($this->isStrongSecret(config('services.ses.key')), 'AWS_ACCESS_KEY_ID must be set to a non-placeholder secret.', $errors);
        $this->require($this->isStrongSecret(config('services.ses.secret')), 'AWS_SECRET_ACCESS_KEY must be set to a non-placeholder secret.', $errors);
        $this->require($this->nonEmpty(config('services.ses.region')), 'AWS_DEFAULT_REGION must be set.', $errors);
    }

    private function validAppKey(mixed $value): bool
    {
        if (! is_string($value) || ! str_starts_with($value, 'base64:')) {
            return false;
        }

        $decoded = base64_decode(substr($value, 7), true);

        return is_string($decoded) && strlen($decoded) === 32 && $value !== 'base64:rT5TT1yPZHA/yjxlfDvQ59Z4Jt3zqpS7OEqrDTUxaG0=';
    }

    private function isHttpsUrl(mixed $value): bool
    {
        return $this->isHttpUrl($value) && parse_url((string) $value, PHP_URL_SCHEME) === 'https';
    }

    private function isHttpUrl(mixed $value): bool
    {
        if (! is_string($value) || filter_var($value, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        return in_array(parse_url($value, PHP_URL_SCHEME), ['http', 'https'], true);
    }

    private function validDomain(mixed $value): bool
    {
        return is_string($value)
            && strlen($value) <= 253
            && preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i', $value) === 1;
    }

    private function isStrongSecret(mixed $value): bool
    {
        if (! is_string($value) || strlen($value) < 16) {
            return false;
        }

        return preg_match('/example|change.?me|placeholder/i', $value) !== 1;
    }

    private function nonEmpty(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }

    /** @param array<int, string> $errors */
    private function require(bool $condition, string $message, array &$errors): void
    {
        if (! $condition) {
            $errors[] = $message;
        }
    }
}
