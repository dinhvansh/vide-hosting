<?php

namespace App\Exceptions;

class ProviderException extends PlatformException
{
    /** @param array<string, mixed> $details */
    public function __construct(string $errorCode = 'PROVIDER_UNAVAILABLE', string $message = 'The deployment provider is temporarily unavailable.', int $httpStatus = 503, array $details = [], ?\Throwable $previous = null)
    {
        parent::__construct($errorCode, $message, $httpStatus, $details, $previous);
    }
}
