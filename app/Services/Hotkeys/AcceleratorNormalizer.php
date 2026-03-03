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
     * Convert a stored/normalized accelerator to the NativePHP registration format.
     *
     * DB format  : cmd+ctrl+alt+shift+a   (lowercase, short aliases)
     * NativePHP  : Cmd+Ctrl+Alt+Shift+A   (Title-case modifiers, upper-case key letter)
     *
     * Modifier mapping (per docs/GLOBAL_HOTKEYS.md):
     *   cmd   → Cmd      ctrl  → Ctrl
     *   alt   → Alt      shift → Shift
     *
     * Key:
     *   Single letters are upper-cased (A-Z).
     *   Multi-character keys are Title-cased (Space, F13, Up, Down, Esc …).
     */
    public static function toNativeFormat(string $normalized): string
    {
        $modifierMap = [
            'cmd'   => 'Cmd',
            'ctrl'  => 'Ctrl',
            'alt'   => 'Alt',
            'shift' => 'Shift',
        ];

        $parts = array_map('trim', explode('+', strtolower($normalized)));

        $converted = array_map(function (string $part) use ($modifierMap): string {
            if (isset($modifierMap[$part])) {
                return $modifierMap[$part];
            }

            // Single letter → upper-case
            if (strlen($part) === 1) {
                return strtoupper($part);
            }

            // Multi-char key (F13, Space, Up, Esc, …) → Title-case
            return ucfirst($part);
        }, $parts);

        return implode('+', $converted);
    }
}

