<?php

namespace App\Services\Obs\Exceptions;

use Exception;

class ObsProtocolError extends Exception
{
    public function __construct(string $message = 'OBS protocol error')
    {
        parent::__construct($message);
    }
}

