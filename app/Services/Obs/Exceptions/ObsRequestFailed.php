<?php

namespace App\Services\Obs\Exceptions;

use Exception;

class ObsRequestFailed extends Exception
{
    public readonly int $obsCode;
    public readonly string $obsMessage;

    public function __construct(
        int $code,
        string $obsMessage,
        string $requestType = 'unknown'
    ) {
        $this->obsCode = $code;
        $this->obsMessage = $obsMessage;
        parent::__construct("OBS request failed: {$requestType} (code {$code}): {$obsMessage}", $code);
    }
}
