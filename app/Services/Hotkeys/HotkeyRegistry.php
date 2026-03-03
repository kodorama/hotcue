<?php

namespace App\Services\Hotkeys;

use App\Events\HotkeyPressed;
use App\Models\Hotkey;
use Illuminate\Support\Facades\Log;
use Native\Desktop\Facades\GlobalShortcut;

class HotkeyRegistry
{
    /** @var array<int, string> hotkeyId → NativePHP accelerator string */
    private array $registeredHotkeys = [];

    /**
     * Load and register all enabled hotkeys from database.
     */
    public function registerAll(): void
    {
        $hotkeys = Hotkey::query()->enabled()->get();

        Log::info('HotkeyRegistry: loading enabled hotkeys', ['count' => $hotkeys->count()]);

        $this->unregisterAll();

        foreach ($hotkeys as $hotkey) {
            $this->register($hotkey);
        }

        Log::info('HotkeyRegistry: registration complete', ['registered' => count($this->registeredHotkeys)]);
    }

    /**
     * Register a single hotkey.
     *
     * NativePHP fires the event class passed to ->event() when the shortcut is
     * triggered. We pass HotkeyPressed::class so Electron calls:
     *   new HotkeyPressed($hotkeyId)  →  event(HotkeyPressed)
     * HandleHotkeyPressed listener then resolves the action and calls ObsActionRunner.
     */
    public function register(Hotkey $hotkey): bool
    {
        $stored     = $hotkey->accelerator;
        $native     = AcceleratorNormalizer::toNativeFormat($stored);
        $eventClass = HotkeyPressed::class;

        Log::debug('HotkeyRegistry: registering hotkey', [
            'id'           => $hotkey->id,
            'name'         => $hotkey->name,
            'stored_accel' => $stored,
            'native_accel' => $native,
            'event_class'  => $eventClass,
        ]);

        try {
            GlobalShortcut::key($native)
                ->event($eventClass)
                ->register();

            $this->registeredHotkeys[$hotkey->id] = $native;

            Log::info('HotkeyRegistry: hotkey registered', [
                'id'    => $hotkey->id,
                'name'  => $hotkey->name,
                'accel' => $native,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('HotkeyRegistry: failed to register hotkey', [
                'id'    => $hotkey->id,
                'name'  => $hotkey->name,
                'accel' => $native,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Unregister a single hotkey by id.
     */
    public function unregister(int $hotkeyId): void
    {
        if (!isset($this->registeredHotkeys[$hotkeyId])) {
            return;
        }

        $accelerator = $this->registeredHotkeys[$hotkeyId];

        try {
            // Correct NativePHP API: GlobalShortcut::key($accel)->unregister()
            // NOT GlobalShortcut::unregister($accel) — that method does not exist.
            GlobalShortcut::key($accelerator)->unregister();
            unset($this->registeredHotkeys[$hotkeyId]);

            Log::debug('HotkeyRegistry: unregistered hotkey', [
                'id'    => $hotkeyId,
                'accel' => $accelerator,
            ]);
        } catch (\Throwable $e) {
            Log::error('HotkeyRegistry: failed to unregister hotkey', [
                'id'    => $hotkeyId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Unregister all hotkeys from Electron.
     *
     * We query the DB for every enabled hotkey and explicitly unregister each
     * one, regardless of what $registeredHotkeys contains. This is critical
     * because NativePHP runs PHP as `php -S` (a fresh process per request), so
     * the in-memory array is always empty on each new request. Without hitting
     * the DB here, calling unregisterAll() before registerAll() would be a
     * no-op and Electron would accumulate duplicate registrations — causing
     * every hotkey to fire its action twice per keypress.
     */
    public function unregisterAll(): void
    {
        // Unregister whatever we have tracked in-memory first
        foreach (array_keys($this->registeredHotkeys) as $hotkeyId) {
            $this->unregister($hotkeyId);
        }

        // Also unregister every enabled hotkey directly from the DB so we
        // clean up Electron-side registrations from previous PHP processes.
        $allEnabled = Hotkey::query()->enabled()->get();
        foreach ($allEnabled as $hotkey) {
            $native = AcceleratorNormalizer::toNativeFormat($hotkey->accelerator);
            // Skip if we already unregistered it via the in-memory map above
            if (isset($this->registeredHotkeys[$hotkey->id])) {
                continue;
            }
            try {
                GlobalShortcut::key($native)->unregister();
                Log::debug('HotkeyRegistry: unregistered stale hotkey from Electron', [
                    'id'    => $hotkey->id,
                    'accel' => $native,
                ]);
            } catch (\Throwable $e) {
                // Electron returns 200 even when the key wasn't registered, so
                // this should rarely throw — log and continue regardless.
                Log::debug('HotkeyRegistry: unregister returned error (may not have been registered)', [
                    'id'    => $hotkey->id,
                    'accel' => $native,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->registeredHotkeys = [];
        Log::debug('HotkeyRegistry: unregistered all hotkeys from Electron');
    }

    /**
     * Check if accelerator is already registered.
     */
    public function isAcceleratorRegistered(string $accelerator, ?int $excludeHotkeyId = null): bool
    {
        $normalized = AcceleratorNormalizer::normalize($accelerator);

        return Hotkey::query()->enabled()
            ->where('accelerator', $normalized)
            ->when($excludeHotkeyId, fn($q) => $q->where('id', '!=', $excludeHotkeyId))
            ->exists();
    }
}

