<?php

namespace App\Services\Hotkeys;

use App\Models\Hotkey;
use App\Models\HotkeyAction;

class HotkeyExporter
{
    /**
     * Export all hotkeys (with their actions) to a JSON string.
     */
    public function export(): string
    {
        $hotkeys = Hotkey::query()->with('actions')->orderBy('name')->get();

        $data = [
            'version'  => 1,
            'exported' => now()->toIso8601String(),
            'hotkeys'  => $hotkeys->map(fn(Hotkey $h) => [
                'name'        => $h->name,
                'accelerator' => $h->accelerator,
                'enabled'     => $h->enabled,
                'actions'     => $h->actions->map(fn(HotkeyAction $a) => [
                    'type'    => $a->type,
                    'payload' => $a->payload ?? [],
                ])->values()->toArray(),
            ])->values()->toArray(),
        ];

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Import hotkeys from a JSON string.
     *
     * @param  string $json  Raw JSON content
     * @param  bool   $merge true = add to existing; false = replace all
     * @return array{imported: int, skipped: int, errors: string[]}
     */
    public function import(string $json, bool $merge = true): array
    {
        $result = ['imported' => 0, 'skipped' => 0, 'errors' => []];

        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $result['errors'][] = 'Invalid JSON: ' . $e->getMessage();
            return $result;
        }

        if (!isset($data['hotkeys']) || !is_array($data['hotkeys'])) {
            $result['errors'][] = 'Missing or invalid "hotkeys" key in import file.';
            return $result;
        }

        if (!$merge) {
            Hotkey::query()->delete(); // cascade deletes actions
        }

        foreach ($data['hotkeys'] as $i => $row) {
            try {
                $this->importOne($row, $result);
            } catch (\Throwable $e) {
                $result['errors'][] = "Row {$i}: " . $e->getMessage();
                $result['skipped']++;
            }
        }

        return $result;
    }

    private function importOne(array $row, array &$result): void
    {
        $name        = trim($row['name'] ?? '');
        $accelerator = trim($row['accelerator'] ?? '');
        $enabled     = (bool) ($row['enabled'] ?? true);
        $actions     = $row['actions'] ?? [];

        if ($name === '' || $accelerator === '') {
            $result['errors'][] = "Skipped row with missing name or accelerator.";
            $result['skipped']++;
            return;
        }

        // Normalize accelerator
        $normalized = AcceleratorNormalizer::normalize($accelerator);

        // Upsert: if a hotkey with this accelerator already exists, update it
        $hotkey = Hotkey::query()->firstOrNew(['accelerator' => $normalized]);
        $hotkey->name    = $name;
        $hotkey->enabled = $enabled;
        $hotkey->save();

        // Replace actions
        $hotkey->actions()->delete();
        foreach ($actions as $action) {
            $type    = $action['type'] ?? '';
            $payload = $action['payload'] ?? [];
            if ($type === '') {
                continue;
            }
            $hotkey->actions()->create(['type' => $type, 'payload' => $payload]);
        }

        $result['imported']++;
    }
}

