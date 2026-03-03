<?php

namespace App\Services\Obs\Exceptions;

use Exception;

class ObsAuthFailed extends Exception
{
    public function __construct(string $message = 'OBS authentication failed')
    {
        parent::__construct($message);
    }
}

