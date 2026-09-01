<?php

namespace App\Services;

use App\Models\Application;
use Throwable;

class ApplicationLogRedactor
{
    public function redact(Application $application, string $logs): string
    {
        try {
            $secrets = $application->environmentVariables()->where('is_secret', true)->get()
                ->flatMap(function ($variable): array {
                    $value = (string) $variable->encrypted_value;
                    if ($value === '') {
                        return [];
                    }

                    return array_unique([$value, rawurlencode($value), urlencode($value), base64_encode($value)]);
                })->filter(fn (string $value): bool => $value !== '')->sortByDesc(fn (string $value): int => strlen($value))->values()->all();
        } catch (Throwable $exception) {
            report($exception);

            return '[VIVE_LOGS_HIDDEN: SECRET_DECRYPTION_FAILED]';
        }

        return $secrets === [] ? $logs : str_replace($secrets, '[REDACTED]', $logs);
    }
}
