<?php

namespace App\Services\Obs;

use App\Services\Obs\Exceptions\ObsNotConnected;
use Illuminate\Support\Facades\Log;
use React\Promise\PromiseInterface;

class ObsActionRunner
{
    private array $cachedVolumes = [];

    public function __construct(
        private readonly ObsConnectionManager $connectionManager
    ) {
    }

    /**
     * Execute an action against OBS.
     */
    public function executeAction(string $type, array $payload): PromiseInterface
    {
        if (!$this->connectionManager->isConnected()) {
            Log::warning('Cannot execute action: not connected to OBS', ['type' => $type]);
            return \React\Promise\reject(new ObsNotConnected());
        }

        return match ($type) {
            'switch_scene'      => $this->switchScene($payload),
            'toggle_mute'       => $this->toggleMute($payload),
            'set_mute'          => $this->setMute($payload),
            'adjust_volume_db'  => $this->adjustVolumeDb($payload),
            'set_volume_db'     => $this->setVolumeDb($payload),
            'start_streaming'   => $this->connectionManager->sendRequest('StartStream'),
            'stop_streaming'    => $this->connectionManager->sendRequest('StopStream'),
            'toggle_streaming'  => $this->connectionManager->sendRequest('ToggleStream'),
            'start_recording'   => $this->connectionManager->sendRequest('StartRecord'),
            'stop_recording'    => $this->connectionManager->sendRequest('StopRecord'),
            'toggle_recording'  => $this->connectionManager->sendRequest('ToggleRecord'),
            'pause_recording'   => $this->connectionManager->sendRequest('PauseRecord'),
            'resume_recording'  => $this->connectionManager->sendRequest('ResumeRecord'),
            default => \React\Promise\reject(new \InvalidArgumentException("Unknown action type: {$type}")),
        };
    }

    /**
     * Validate action payload before execution.
     */
    public function validatePayload(string $type, array $payload): array
    {
        return match ($type) {
            'switch_scene'      => $this->validateSwitchScene($payload),
            'toggle_mute'       => $this->validateToggleMute($payload),
            'set_mute'          => $this->validateSetMute($payload),
            'adjust_volume_db'  => $this->validateAdjustVolumeDb($payload),
            'set_volume_db'     => $this->validateSetVolumeDb($payload),
            // No-payload actions — always valid
            'start_streaming',
            'stop_streaming',
            'toggle_streaming',
            'start_recording',
            'stop_recording',
            'toggle_recording',
            'pause_recording',
            'resume_recording'  => [],
            default             => ["Unknown action type: {$type}"],
        };
    }

    private function switchScene(array $payload): PromiseInterface
    {
        return $this->connectionManager->sendRequest('SetCurrentProgramScene', [
            'sceneName' => $payload['scene'],
        ]);
    }

    private function toggleMute(array $payload): PromiseInterface
    {
        return $this->connectionManager->sendRequest('ToggleInputMute', [
            'inputName' => $payload['input'],
        ]);
    }

    private function setMute(array $payload): PromiseInterface
    {
        return $this->connectionManager->sendRequest('SetInputMute', [
            'inputName'  => $payload['input'],
            'inputMuted' => $payload['muted'],
        ]);
    }

    private function adjustVolumeDb(array $payload): PromiseInterface
    {
        $inputName = $payload['input'];
        $deltaDb   = $payload['deltaDb'];

        return $this->connectionManager->sendRequest('GetInputVolume', [
            'inputName' => $inputName,
        ])->then(function ($data) use ($inputName, $deltaDb) {
            $currentDb = $data['inputVolumeDb'] ?? 0;
            $newDb     = $currentDb + $deltaDb;

            return $this->connectionManager->sendRequest('SetInputVolume', [
                'inputName'      => $inputName,
                'inputVolumeDb'  => $newDb,
            ]);
        });
    }

    private function setVolumeDb(array $payload): PromiseInterface
    {
        return $this->connectionManager->sendRequest('SetInputVolume', [
            'inputName'     => $payload['input'],
            'inputVolumeDb' => $payload['db'],
        ]);
    }

    // Validation methods
    private function validateSwitchScene(array $payload): array
    {
        return empty($payload['scene']) ? ['Scene name is required'] : [];
    }

    private function validateToggleMute(array $payload): array
    {
        return empty($payload['input']) ? ['Input name is required'] : [];
    }

    private function validateSetMute(array $payload): array
    {
        $errors = [];
        if (empty($payload['input'])) {
            $errors[] = 'Input name is required';
        }
        if (!isset($payload['muted']) || !is_bool($payload['muted'])) {
            $errors[] = 'Muted must be a boolean';
        }
        return $errors;
    }

    private function validateAdjustVolumeDb(array $payload): array
    {
        $errors = [];
        if (empty($payload['input'])) {
            $errors[] = 'Input name is required';
        }
        if (!isset($payload['deltaDb']) || !is_numeric($payload['deltaDb'])) {
            $errors[] = 'Delta dB must be numeric';
        }
        return $errors;
    }

    private function validateSetVolumeDb(array $payload): array
    {
        $errors = [];
        if (empty($payload['input'])) {
            $errors[] = 'Input name is required';
        }
        if (!isset($payload['db']) || !is_numeric($payload['db'])) {
            $errors[] = 'dB value must be numeric';
        }
        return $errors;
    }
}

