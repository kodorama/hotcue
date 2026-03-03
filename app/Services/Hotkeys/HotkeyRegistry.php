<?php

namespace App\Services\Hotkeys;

use App\Models\Hotkey;
use Illuminate\Support\Facades\Log;
use Native\Desktop\Facades\GlobalShortcut;

class HotkeyRegistry
{
    private array $registeredHotkeys = [];

    /**
     * Load and register all enabled hotkeys from database.
     */
    public function registerAll(): void
    {
        $this->unregisterAll();

        $hotkeys = Hotkey::enabled()->get();

        foreach ($hotkeys as $hotkey) {
            $this->register($hotkey);
        }

        Log::info('Registered hotkeys', ['count' => count($this->registeredHotkeys)]);
    }

    /**
     * Register a single hotkey.
     */
    public function register(Hotkey $hotkey): bool
    {
        try {
            $accelerator = AcceleratorNormalizer::toNativeFormat($hotkey->accelerator);

            GlobalShortcut::key($accelerator)
                ->event($hotkey->id)
                ->register();

            $this->registeredHotkeys[$hotkey->id] = $accelerator;

            Log::debug('Registered hotkey', [
                'id' => $hotkey->id,
                'name' => $hotkey->name,
                'accelerator' => $accelerator,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('Failed to register hotkey', [
                'id' => $hotkey->id,
                'name' => $hotkey->name,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Unregister a single hotkey.
     */
    public function unregister(int $hotkeyId): void
    {
        if (!isset($this->registeredHotkeys[$hotkeyId])) {
            return;
        }

        try {
            $accelerator = $this->registeredHotkeys[$hotkeyId];
            GlobalShortcut::unregister($accelerator);
            unset($this->registeredHotkeys[$hotkeyId]);

            Log::debug('Unregistered hotkey', ['id' => $hotkeyId, 'accelerator' => $accelerator]);
        } catch (\Throwable $e) {
            Log::error('Failed to unregister hotkey', ['id' => $hotkeyId, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Unregister all hotkeys.
     */
    public function unregisterAll(): void
    {
        foreach (array_keys($this->registeredHotkeys) as $hotkeyId) {
            $this->unregister($hotkeyId);
        }

        Log::debug('Unregistered all hotkeys');
    }

    /**
     * Check if accelerator is already registered.
     */
    public function isAcceleratorRegistered(string $accelerator, ?int $excludeHotkeyId = null): bool
    {
        $normalized = AcceleratorNormalizer::normalize($accelerator);

        return Hotkey::enabled()
            ->where('accelerator', $normalized)
            ->when($excludeHotkeyId, fn($q) => $q->where('id', '!=', $excludeHotkeyId))
            ->exists();
    }
}

