<?php

namespace App\Services\Obs\Contracts;

use React\Promise\PromiseInterface;

interface ObsClient
{
    /**
     * Check if connected to OBS.
     */
    public function isConnected(): bool;

    /**
     * Send a request to OBS and return a promise.
     *
     * @param string $requestType OBS v5 request type
     * @param array $requestData Request parameters
     * @return PromiseInterface Promise that resolves to response data array
     */
    public function sendRequest(string $requestType, array $requestData = []): PromiseInterface;

    /**
     * Close the connection.
     */
    public function close(): void;
}

