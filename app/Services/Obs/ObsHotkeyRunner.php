<?php

namespace App\Services\Obs;

use App\Models\HotkeyAction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use React\EventLoop\StreamSelectLoop;

/**
 * Executes one or more hotkey actions against OBS using an isolated,
 * per-request ReactPHP event loop.
 *
 * WHY THIS EXISTS
 * ---------------
 * NativePHP's PHP server runs as `php -S`, which forks a fresh PHP process
 * for every HTTP request. Singletons (ObsConnectionManager, ObsActionRunner)
 * are therefore re-instantiated on each request with no prior connection.
 * Rather than relying on a persistent connection that can never survive a
 * request boundary, we connect, execute all actions, and disconnect within
 * the same request — identical to the pattern used by ObsDiagnosticsService
 * for the "Test" button (which already works correctly).
 */
class ObsHotkeyRunner
{
    private const TIMEOUT_SECONDS = 12;

    /**
     * Run all actions for a hotkey synchronously within an isolated event loop.
     * Returns when all actions have completed (or timed out / errored).
     *
     * @param  Collection<HotkeyAction>  $actions
     */
    public function runActions(Collection $actions, string $hotkeyName): void
    {
        if ($actions->isEmpty()) {
            Log::warning('ObsHotkeyRunner: no actions to run', ['hotkey' => $hotkeyName]);
            return;
        }

        $settings = \App\Models\Setting::instance();

        $protocol = $settings->ws_secure ? 'wss' : 'ws';
        $uri      = "{$protocol}://{$settings->ws_host}:{$settings->ws_port}";

        Log::info('ObsHotkeyRunner: connecting for hotkey', [
            'hotkey' => $hotkeyName,
            'uri'    => $uri,
            'count'  => $actions->count(),
        ]);

        // Isolated loop — never touches the global singleton loop
        $loop   = new StreamSelectLoop();
        $client = new ObsWebSocketClient($loop, $uri, $settings->ws_password);
        $done   = false;

        // Build a sequential promise chain: connect → action1 → action2 → …
        $chain = $client->connect();

        foreach ($actions as $action) {
            $chain = $chain->then(function () use ($client, $action, $hotkeyName) {
                return $this->executeAction($client, $action, $hotkeyName);
            });
        }

        $chain
            ->catch(function (\Throwable $e) use ($hotkeyName) {
                Log::error('ObsHotkeyRunner: action chain failed', [
                    'hotkey' => $hotkeyName,
                    'error'  => $e->getMessage(),
                    'trace'  => $e->getTraceAsString(),
                ]);
            })
            ->finally(function () use ($client, $loop, &$done) {
                $client->close();
                $done = true;
                $loop->stop();
            });

        // Safety timeout — stop the loop if actions hang
        $loop->addTimer(self::TIMEOUT_SECONDS, function () use ($loop, &$done, $hotkeyName) {
            if (!$done) {
                Log::error('ObsHotkeyRunner: timed out waiting for actions', [
                    'hotkey'  => $hotkeyName,
                    'timeout' => self::TIMEOUT_SECONDS,
                ]);
                $loop->stop();
            }
        });

        try {
            $loop->run();
        } catch (\Throwable $e) {
            Log::error('ObsHotkeyRunner: event loop exception', [
                'hotkey' => $hotkeyName,
                'error'  => $e->getMessage(),
            ]);
        }
    }

    /**
     * Execute a single action against an already-connected client.
     */
    private function executeAction(ObsWebSocketClient $client, HotkeyAction $action, string $hotkeyName): \React\Promise\PromiseInterface
    {
        Log::debug('ObsHotkeyRunner: executing action', [
            'hotkey'  => $hotkeyName,
            'type'    => $action->type,
            'payload' => $action->payload,
        ]);

        $p = $this->buildRequest($client, $action->type, $action->payload ?? []);

        return $p->then(
            function ($result) use ($action, $hotkeyName) {
                Log::info('ObsHotkeyRunner: action succeeded', [
                    'hotkey' => $hotkeyName,
                    'type'   => $action->type,
                ]);
                return $result;
            },
            function (\Throwable $e) use ($action, $hotkeyName) {
                Log::error('ObsHotkeyRunner: action failed', [
                    'hotkey' => $hotkeyName,
                    'type'   => $action->type,
                    'error'  => $e->getMessage(),
                ]);
                // Re-throw so the chain's catch handler also sees it
                throw $e;
            }
        );
    }

    /**
     * Map action type + payload to an OBS WebSocket request.
     *
     * For adjust_volume_db we perform the two-step Get→Set inside a single
     * promise chain on the already-open connection.
     */
    private function buildRequest(ObsWebSocketClient $client, string $type, array $payload): \React\Promise\PromiseInterface
    {
        return match ($type) {
            'switch_scene' => $client->sendRequest('SetCurrentProgramScene', [
                'sceneName' => $payload['scene'] ?? '',
            ]),
            'toggle_mute' => $client->sendRequest('ToggleInputMute', [
                'inputName' => $payload['input'] ?? '',
            ]),
            'set_mute' => $client->sendRequest('SetInputMute', [
                'inputName'  => $payload['input'] ?? '',
                'inputMuted' => (bool) ($payload['muted'] ?? false),
            ]),
            'set_volume_db' => $client->sendRequest('SetInputVolume', [
                'inputName'     => $payload['input'] ?? '',
                'inputVolumeDb' => (float) ($payload['db'] ?? 0),
            ]),
            'adjust_volume_db' => $client->sendRequest('GetInputVolume', [
                'inputName' => $payload['input'] ?? '',
            ])->then(function (array $data) use ($client, $payload) {
                $current = $data['inputVolumeDb'] ?? 0.0;
                $newDb   = $current + (float) ($payload['deltaDb'] ?? 0);
                return $client->sendRequest('SetInputVolume', [
                    'inputName'     => $payload['input'] ?? '',
                    'inputVolumeDb' => $newDb,
                ]);
            }),
            'start_streaming'  => $client->sendRequest('StartStream'),
            'stop_streaming'   => $client->sendRequest('StopStream'),
            'toggle_streaming' => $client->sendRequest('ToggleStream'),
            'start_recording'  => $client->sendRequest('StartRecord'),
            'stop_recording'   => $client->sendRequest('StopRecord'),
            'toggle_recording' => $client->sendRequest('ToggleRecord'),
            'pause_recording'  => $client->sendRequest('PauseRecord'),
            'resume_recording' => $client->sendRequest('ResumeRecord'),
            default => \React\Promise\reject(
                new \InvalidArgumentException("ObsHotkeyRunner: unknown action type '{$type}'")
            ),
        };
    }
}

