<?php

namespace App\Services\Hotkeys;

use App\Models\Hotkey;
use App\Services\Obs\ObsActionRunner;
use Illuminate\Support\Facades\Log;

class HotkeyDispatcher
{
    public function __construct(
        private readonly ObsActionRunner $actionRunner
    ) {
    }

    /**
     * Dispatch a hotkey press event.
     */
    public function dispatch(int $hotkeyId): void
    {
        $hotkey = Hotkey::with('actions')->find($hotkeyId);

        if (!$hotkey || !$hotkey->enabled) {
            Log::warning('Hotkey not found or disabled', ['id' => $hotkeyId]);
            return;
        }

        Log::debug('Hotkey pressed', ['id' => $hotkeyId, 'name' => $hotkey->name]);

        foreach ($hotkey->actions as $action) {
            $this->executeAction($action);
        }
    }

    private function executeAction($action): void
    {
        try {
            $this->actionRunner->executeAction($action->type, $action->payload)
                ->then(
                    function ($result) use ($action) {
                        Log::debug('Action executed successfully', [
                            'type' => $action->type,
                            'result' => $result,
                        ]);
                    },
                    function (\Throwable $e) use ($action) {
                        Log::error('Action execution failed', [
                            'type' => $action->type,
                            'error' => $e->getMessage(),
                        ]);
                    }
                );
        } catch (\Throwable $e) {
            Log::error('Failed to execute action', [
                'type' => $action->type,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

