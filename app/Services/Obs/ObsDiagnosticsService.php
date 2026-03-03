<?php

namespace App\Services\Obs;

use Illuminate\Support\Facades\Log;

class ObsDiagnosticsService
{
    /**
     * Test connection to OBS and retrieve version info.
     *
     * Creates an isolated event loop per test so the shared app loop is never stopped.
     *
     * @return array{success: bool, version: string|null, rpcVersion: string|null, error: string|null}
     */
    public function testConnection(string $host, int $port, ?string $password, bool $secure = false): array
    {
        $result = [
            'success' => false,
            'version' => null,
            'rpcVersion' => null,
            'error' => null,
        ];

        $protocol = $secure ? 'wss' : 'ws';
        $uri = "{$protocol}://{$host}:{$port}";

        // Use a fresh, isolated StreamSelectLoop — never touch or replace the shared singleton loop.
        // (Factory::create() calls Loop::set() which would overwrite the global loop — do NOT use it here.)
        $loop = new \React\EventLoop\StreamSelectLoop();
        $client = new ObsWebSocketClient($loop, $uri, $password);

        $done = false;

        $client->connect()
            ->then(fn() => $client->sendRequest('GetVersion'))
            ->then(function (array $data) use (&$result) {
                $result['success'] = true;
                $result['version'] = $data['obsVersion'] ?? 'unknown';
                $result['rpcVersion'] = $data['obsWebSocketVersion'] ?? null;
            })
            ->catch(function (\Throwable $e) use (&$result) {
                $result['error'] = $e->getMessage();
                Log::debug('OBS diagnostics: test failed', ['error' => $e->getMessage()]);
            })
            ->finally(function () use ($client, $loop, &$done) {
                $client->close();
                $done = true;
                $loop->stop();
            });

        // Safety: stop loop after 15 seconds regardless
        $loop->addTimer(15, function () use ($loop, &$done, &$result) {
            if (!$done) {
                $result['error'] = 'Connection timed out (15s)';
                $loop->stop();
            }
        });

        try {
            $loop->run();
        } catch (\Throwable $e) {
            $result['error'] = $e->getMessage();
            Log::error('OBS diagnostics: loop exception', ['error' => $e->getMessage()]);
        }

        return $result;
    }

    /**
     * Execute a single OBS action via an isolated event loop.
     * Used by the UI "Test" button to fire a hotkey action immediately.
     *
     * @return array{success: bool, error: string|null}
     */
    public function runAction(
        string $host,
        int $port,
        ?string $password,
        bool $secure,
        string $actionType,
        array $actionPayload
    ): array {
        $result = ['success' => false, 'error' => null];

        $obsRequestType = $this->actionTypeToObsRequest($actionType);
        if ($obsRequestType === null) {
            return ['success' => false, 'error' => "Unknown action type: {$actionType}"];
        }

        $obsRequestData = $this->actionPayloadToObsData($actionType, $actionPayload);

        $protocol = $secure ? 'wss' : 'ws';
        $uri      = "{$protocol}://{$host}:{$port}";

        $loop   = new \React\EventLoop\StreamSelectLoop();
        $client = new ObsWebSocketClient($loop, $uri, $password);
        $done   = false;

        $client->connect()
            ->then(fn() => $client->sendRequest($obsRequestType, $obsRequestData))
            ->then(function () use (&$result) {
                $result['success'] = true;
            })
            ->catch(function (\Throwable $e) use (&$result) {
                $result['error'] = $e->getMessage();
            })
            ->finally(function () use ($client, $loop, &$done) {
                $client->close();
                $done = true;
                $loop->stop();
            });

        $loop->addTimer(12, function () use ($loop, &$done, &$result) {
            if (!$done) {
                $result['error'] = 'Request timed out (12s)';
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

    /**
     * Map action type to OBS WebSocket v5 request type.
     */
    private function actionTypeToObsRequest(string $type): ?string
    {
        return match ($type) {
            'switch_scene'     => 'SetCurrentProgramScene',
            'toggle_mute'      => 'ToggleInputMute',
            'set_mute'         => 'SetInputMute',
            'set_volume_db'    => 'SetInputVolume',
            'adjust_volume_db' => 'GetInputVolume', // simplified — get only; full two-step not needed for test
            'start_streaming'  => 'StartStream',
            'stop_streaming'   => 'StopStream',
            'toggle_streaming' => 'ToggleStream',
            'start_recording'  => 'StartRecord',
            'stop_recording'   => 'StopRecord',
            'toggle_recording' => 'ToggleRecord',
            'pause_recording'  => 'PauseRecord',
            'resume_recording' => 'ResumeRecord',
            default            => null,
        };
    }

    /**
     * Convert UI action payload to OBS request data.
     */
    private function actionPayloadToObsData(string $type, array $payload): array
    {
        return match ($type) {
            'switch_scene'  => ['sceneName'    => $payload['scene'] ?? ''],
            'toggle_mute'   => ['inputName'    => $payload['input'] ?? ''],
            'set_mute'      => ['inputName'    => $payload['input'] ?? '', 'inputMuted' => (bool) ($payload['muted'] ?? false)],
            'set_volume_db' => ['inputName'    => $payload['input'] ?? '', 'inputVolumeDb' => (float) ($payload['db'] ?? 0)],
            default         => [],
        };
    }
}
