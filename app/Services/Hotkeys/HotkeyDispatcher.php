<?php

namespace App\Services\Hotkeys;

use App\Models\Hotkey;
use App\Services\Obs\ObsHotkeyRunner;
use Illuminate\Support\Facades\Log;

/**
 * Resolves a hotkey by ID, loads its actions, and delegates execution to
 * ObsHotkeyRunner which opens a fresh, isolated ReactPHP event loop per
 * request.
 *
 * WHY NOT ObsActionRunner?
 * NativePHP runs PHP as `php -S` — every HTTP request (including the
 * /dispatch-event call that fires when a hotkey is pressed) is a brand-new
 * PHP process. The shared ObsConnectionManager singleton therefore always
 * starts disconnected. ObsHotkeyRunner mirrors the ObsDiagnosticsService
 * pattern: connect → act → disconnect, all within the single request.
 */
class HotkeyDispatcher
{
    public function __construct(
        private readonly ObsHotkeyRunner $runner,
    ) {}

    /**
     * Dispatch a hotkey press by id.
     * Called from HandleHotkeyPressed listener when NativePHP fires HotkeyPressed.
     */
    public function dispatch(int $hotkeyId): void
    {
        Log::info('HotkeyDispatcher: hotkey pressed', ['id' => $hotkeyId]);

        $hotkey = Hotkey::query()->with('actions')->find($hotkeyId);

        if (!$hotkey) {
            Log::warning('HotkeyDispatcher: hotkey not found in DB', ['id' => $hotkeyId]);
            return;
        }

        if (!$hotkey->enabled) {
            Log::warning('HotkeyDispatcher: hotkey is disabled, skipping', [
                'id'   => $hotkeyId,
                'name' => $hotkey->name,
            ]);
            return;
        }

        Log::info('HotkeyDispatcher: dispatching actions', [
            'id'           => $hotkeyId,
            'name'         => $hotkey->name,
            'action_count' => $hotkey->actions->count(),
        ]);

        // ObsHotkeyRunner handles connect/execute/disconnect within an isolated
        // event loop — safe to call synchronously from this HTTP request.
        $this->runner->runActions($hotkey->actions, $hotkey->name);
    }
}
