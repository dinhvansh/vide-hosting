<?php

namespace App\Exceptions;

class CapacityException extends PlatformException
{
    public function __construct()
    {
        parent::__construct('NO_CAPACITY', 'No deployment node has enough capacity for this application.', 409);
    }
}
