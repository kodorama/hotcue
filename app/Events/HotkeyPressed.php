<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired by NativePHP when a registered global shortcut is triggered.
 *
 * Electron sends: { event: "App\Events\HotkeyPressed", payload: [acceleratorString] }
 * DispatchEventFromAppController spreads payload as constructor args:
 *   new HotkeyPressed(...["Cmd+Shift+A"])  →  HotkeyPressed("Cmd+Shift+A")
 *
 * The accelerator is the NativePHP-format string (e.g. "Cmd+Shift+A") —
 * HandleHotkeyPressed converts it back to the stored DB format to look up the Hotkey.
 */
class HotkeyPressed
{
    use Dispatchable;

    public function __construct(
        public readonly string $accelerator,
    ) {}
}
