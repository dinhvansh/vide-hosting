<?php

namespace App\Exceptions;

use RuntimeException;

class PlatformException extends RuntimeException
{
    /** @param array<string, mixed> $details */
    public function __construct(public string $errorCode, string $message, public int $httpStatus = 422, public array $details = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
