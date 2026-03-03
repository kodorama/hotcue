<?php

namespace App\Services\Obs\Exceptions;

use Exception;

class ObsRequestTimeout extends Exception
{
    public function __construct(string $requestId, string $requestType)
    {
        parent::__construct("OBS request timeout: {$requestType} (ID: {$requestId})");
    }
}

