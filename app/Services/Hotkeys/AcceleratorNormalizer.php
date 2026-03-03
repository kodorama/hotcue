<?php

namespace App\Services\Hotkeys;

class AcceleratorNormalizer
{
    /**
     * Normalize accelerator string to consistent format.
     * Order: cmd+ctrl+alt+shift+{key}
     * Key: lowercased
     */
    public static function normalize(string $accelerator): string
    {
        // Split by + and trim
        $parts = array_map('trim', explode('+', strtolower($accelerator)));

        $modifiers = [];
        $key = null;

        // Separate modifiers from key
        foreach ($parts as $part) {
            if (in_array($part, ['cmd', 'command', 'meta'])) {
                $modifiers['cmd'] = 'cmd';
            } elseif (in_array($part, ['ctrl', 'control'])) {
                $modifiers['ctrl'] = 'ctrl';
            } elseif (in_array($part, ['alt', 'option'])) {
                $modifiers['alt'] = 'alt';
            } elseif (in_array($part, ['shift'])) {
                $modifiers['shift'] = 'shift';
            } else {
                // This is the key
                $key = $part;
            }
        }

        // Order modifiers consistently
        $ordered = [];
        if (isset($modifiers['cmd'])) {
            $ordered[] = 'cmd';
        }
        if (isset($modifiers['ctrl'])) {
            $ordered[] = 'ctrl';
        }
        if (isset($modifiers['alt'])) {
            $ordered[] = 'alt';
        }
        if (isset($modifiers['shift'])) {
            $ordered[] = 'shift';
        }

        if ($key) {
            $ordered[] = $key;
        }

        return implode('+', $ordered);
    }

    /**
     * Convert to NativePHP format for registration.
     */
    public static function toNativeFormat(string $normalized): string
    {
        // NativePHP uses the same format, but might need Command instead of Cmd
        return str_replace('cmd', 'Command', $normalized);
    }
}

