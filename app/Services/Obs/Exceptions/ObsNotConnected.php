<?php

namespace App\Services\Obs\Exceptions;

use Exception;

class ObsNotConnected extends Exception
{
    public function __construct(string $message = 'Not connected to OBS')
    {
        parent::__construct($message);
    }
}

