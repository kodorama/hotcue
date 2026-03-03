<?php

namespace App\Services\Obs;

use Illuminate\Support\Facades\Log;

/**
 * Fetches OBS scene and input lists via a short-lived isolated connection.
 * Results are cached in-memory for the request lifetime.
 */
class ObsSceneService
{

    /**
     * Get available OBS scenes.
     *
     * @return array{success: bool, scenes: string[], error: string|null}
     */
    public function getScenes(string $host, int $port, ?string $password, bool $secure = false): array
    {
        $result = $this->runRequest($host, $port, $password, $secure, 'GetSceneList');

        if (!$result['success']) {
            return ['success' => false, 'scenes' => [], 'error' => $result['error']];
        }

        $scenes = array_column($result['data']['scenes'] ?? [], 'sceneName');
        // OBS returns scenes in reverse order (bottom-first); reverse to show top-first
        $scenes = array_reverse($scenes);


        return ['success' => true, 'scenes' => $scenes, 'error' => null];
    }

    /**
     * Get available OBS audio/video inputs.
     *
     * @return array{success: bool, inputs: string[], error: string|null}
     */
    public function getInputs(string $host, int $port, ?string $password, bool $secure = false): array
    {
        $result = $this->runRequest($host, $port, $password, $secure, 'GetInputList');

        if (!$result['success']) {
            return ['success' => false, 'inputs' => [], 'error' => $result['error']];
        }

        $inputs = array_column($result['data']['inputs'] ?? [], 'inputName');
        sort($inputs);


        return ['success' => true, 'inputs' => $inputs, 'error' => null];
    }

    /**
     * Run a single OBS request on an isolated event loop.
     *
     * @return array{success: bool, data: array, error: string|null}
     */
    private function runRequest(
        string $host,
        int $port,
        ?string $password,
        bool $secure,
        string $requestType,
        array $requestData = []
    ): array {
        $result = ['success' => false, 'data' => [], 'error' => null];

        $protocol = $secure ? 'wss' : 'ws';
        $uri = "{$protocol}://{$host}:{$port}";

        // Use a fresh, isolated StreamSelectLoop — never touch or replace the shared singleton loop.
        // (Factory::create() calls Loop::set() which would overwrite the global loop — do NOT use it here.)
        $loop = new \React\EventLoop\StreamSelectLoop();
        $client = new ObsWebSocketClient($loop, $uri, $password);
        $done = false;

        $client->connect()
            ->then(fn() => $client->sendRequest($requestType, $requestData))
            ->then(function (array $data) use (&$result) {
                $result['success'] = true;
                $result['data'] = $data;
            })
            ->catch(function (\Throwable $e) use (&$result, $requestType) {
                $result['error'] = $e->getMessage();
                Log::debug("OBS {$requestType} failed", ['error' => $e->getMessage()]);
            })
            ->finally(function () use ($client, $loop, &$done) {
                $client->close();
                $done = true;
                $loop->stop();
            });

        // Safety timeout
        $loop->addTimer(12, function () use ($loop, &$done, &$result) {
            if (!$done) {
                $result['error'] = 'Request timed out';
                $loop->stop();
            }
        });

        try {
            $loop->run();
        } catch (\Throwable $e) {
            $result['error'] = $e->getMessage();
        }

        return $result;
    }
}

