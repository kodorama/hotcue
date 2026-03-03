<?php

namespace App\Listeners;

use App\Events\HotkeyPressed;
use App\Models\Hotkey;
use App\Services\Hotkeys\AcceleratorNormalizer;
use App\Services\Hotkeys\HotkeyDispatcher;
use Illuminate\Support\Facades\Log;

/**
 * Listens for HotkeyPressed events fired by NativePHP when a registered global
 * shortcut is triggered, and delegates to HotkeyDispatcher to execute the
 * associated OBS action.
 *
 * NativePHP dispatch flow:
 *   Electron detects keypress
 *   → notifyLaravel('events', { event: "App\Events\HotkeyPressed", payload: ["Cmd+Shift+A"] })
 *   → DispatchEventFromAppController instantiates HotkeyPressed("Cmd+Shift+A") and fires it
 *   → This listener normalizes "Cmd+Shift+A" → "cmd+shift+a" to find the Hotkey in DB
 *   → Calls HotkeyDispatcher::dispatch($hotkeyId)
 */
class HandleHotkeyPressed
{
    public function __construct(
        private readonly HotkeyDispatcher $dispatcher,
    ) {}

    public function handle(HotkeyPressed $event): void
    {
        // Electron sends the NativePHP-format accelerator (e.g. "Cmd+Shift+A").
        // Normalize it to the DB storage format (e.g. "cmd+shift+a") for lookup.
        $normalized = AcceleratorNormalizer::normalize($event->accelerator);

        Log::info('HandleHotkeyPressed: event received', [
            'native_accel'     => $event->accelerator,
            'normalized_accel' => $normalized,
        ]);

        $hotkey = Hotkey::where('accelerator', $normalized)
            ->where('enabled', true)
            ->first();

        if (!$hotkey) {
            Log::warning('HandleHotkeyPressed: no enabled hotkey found for accelerator', [
                'native_accel'     => $event->accelerator,
                'normalized_accel' => $normalized,
            ]);
            return;
        }

        $this->dispatcher->dispatch($hotkey->id);
    }
}
