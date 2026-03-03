<?php

namespace App\Livewire\Settings;

use App\Models\Hotkey;
use App\Models\HotkeyAction;
use App\Models\Setting;
use App\Services\Hotkeys\AcceleratorNormalizer;
use App\Services\Hotkeys\HotkeyExporter;
use App\Services\Obs\ObsSceneService;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Livewire\WithFileUploads;

class HotkeysManager extends Component
{
    use WithFileUploads;

    public $hotkeys = [];
    public bool $showModal = false;
    public ?int $editingId = null;

    public string $name = '';
    public string $accelerator = '';
    public bool $enabled = true;

    // Legacy single-action properties (kept for test compatibility + simple UI)
    public string $actionType = 'switch_scene';
    public array $actionPayload = [];

    // Multi-action support: array of ['type' => string, 'payload' => array]
    public array $actions = [];

    // Discovered OBS resources
    public array $obsScenes = [];
    public array $obsInputs = [];
    public ?string $discoveryError = null;
    public bool $discovering = false;

    // Test action feedback
    public ?string $testResult = null;
    public bool $testing = false;

    // Import / export
    public ?string $importResult = null;
    public $importFile = null;

    public array $actionTypes = [
        'switch_scene'     => 'Switch Scene',
        'toggle_mute'      => 'Toggle Mute',
        'set_mute'         => 'Set Mute',
        'adjust_volume_db' => 'Adjust Volume (dB)',
        'set_volume_db'    => 'Set Volume (dB)',
        'start_streaming'  => 'Start Streaming',
        'stop_streaming'   => 'Stop Streaming',
        'toggle_streaming' => 'Toggle Streaming',
        'start_recording'  => 'Start Recording',
        'stop_recording'   => 'Stop Recording',
        'toggle_recording' => 'Toggle Recording',
        'pause_recording'  => 'Pause Recording',
        'resume_recording' => 'Resume Recording',
    ];

    public function mount(): void
    {
        $this->loadHotkeys();
    }

    public function loadHotkeys(): void
    {
        $this->hotkeys = Hotkey::query()->with('actions')->orderBy('name')->get();
    }

    public function create(): void
    {
        $this->resetForm();
        $this->actions = [['type' => 'switch_scene', 'payload' => []]];
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $hotkey = Hotkey::query()->with('actions')->findOrFail($id);
        $this->editingId = $id;
        $this->name = $hotkey->name;
        $this->accelerator = $hotkey->accelerator;
        $this->enabled = $hotkey->enabled;

        // Load all actions into multi-action array
        $this->actions = $hotkey->actions->map(fn($a) => [
            'type'    => $a->type,
            'payload' => $a->payload ?? [],
        ])->toArray();

        if (empty($this->actions)) {
            $this->actions = [['type' => 'switch_scene', 'payload' => []]];
        }

        // Sync legacy single-action fields from first action (for test compatibility)
        $this->actionType    = $this->actions[0]['type'];
        $this->actionPayload = $this->actions[0]['payload'];

        $this->showModal = true;
    }

    /** Add another action row to the multi-action list. */
    public function addAction(): void
    {
        $this->actions[] = ['type' => 'switch_scene', 'payload' => []];
    }

    /** Remove an action row by index. */
    public function removeAction(int $index): void
    {
        if (count($this->actions) <= 1) {
            return; // Always keep at least one action
        }
        array_splice($this->actions, $index, 1);
        $this->actions = array_values($this->actions);
    }

    public function save(): void
    {
        $this->validate([
            'name'        => 'required|string|max:255',
            'accelerator' => 'required|string',
            'enabled'     => 'boolean',
            'actionType'  => 'required|in:switch_scene,toggle_mute,set_mute,adjust_volume_db,set_volume_db,start_streaming,stop_streaming,toggle_streaming,start_recording,stop_recording,toggle_recording,pause_recording,resume_recording',
        ]);

        $normalized = AcceleratorNormalizer::normalize($this->accelerator);

        // Check for duplicate accelerator among other enabled hotkeys
        $conflict = Hotkey::query()->where('accelerator', $normalized)
            ->where('enabled', true)
            ->when($this->editingId, fn($q) => $q->where('id', '!=', $this->editingId))
            ->exists();

        if ($conflict && $this->enabled) {
            $this->addError('accelerator', 'This accelerator is already used by another enabled hotkey.');
            return;
        }

        // Build the effective actions list:
        // If $actions array is populated (multi-action UI), use it.
        // Otherwise fall back to legacy single actionType/actionPayload fields.
        $effectiveActions = !empty($this->actions)
            ? $this->actions
            : [['type' => $this->actionType, 'payload' => $this->actionPayload]];

        // Sync legacy fields from first action so validation runs correctly
        $this->actionType    = $effectiveActions[0]['type'];
        $this->actionPayload = $effectiveActions[0]['payload'] ?? [];

        // Validate all action payloads
        foreach ($effectiveActions as $i => $act) {
            $errors = $this->validateActionPayload($act['type'] ?? '', $act['payload'] ?? []);
            foreach ($errors as $error) {
                $this->addError('actionPayload', "Action " . ($i + 1) . ": {$error}");
            }
        }
        if ($this->getErrorBag()->has('actionPayload')) {
            return;
        }

        if ($this->editingId) {
            $hotkey = Hotkey::query()->findOrFail($this->editingId);
        } else {
            $hotkey = new Hotkey();
        }

        $hotkey->name        = $this->name;
        $hotkey->accelerator = $normalized;
        $hotkey->enabled     = $this->enabled;
        $hotkey->save();

        // Replace all actions
        $hotkey->actions()->delete();
        foreach ($effectiveActions as $act) {
            HotkeyAction::query()->create([
                'hotkey_id' => $hotkey->id,
                'type'      => $act['type'],
                'payload'   => $act['payload'] ?? [],
            ]);
        }

        $this->syncHotkeyRegistry();

        $this->loadHotkeys();
        $this->closeModal();
        session()->flash('hotkey_saved', 'Hotkey saved successfully.');
    }

    public function delete(int $id): void
    {
        $hotkey = Hotkey::query()->findOrFail($id);
        $this->unregisterHotkey($id);
        $hotkey->delete();
        $this->loadHotkeys();
    }

    public function toggleEnabled(int $id): void
    {
        $hotkey = Hotkey::query()->findOrFail($id);
        $hotkey->enabled = !$hotkey->enabled;
        $hotkey->save();

        $this->syncHotkeyRegistry();
        $this->loadHotkeys();
    }

    /**
     * Fetch scenes and inputs from OBS to populate dropdowns.
     */
    public function discoverObs(ObsSceneService $sceneService): void
    {
        $this->discovering = true;
        $this->discoveryError = null;
        $this->obsScenes = [];
        $this->obsInputs = [];

        $settings = Setting::instance();

        try {
            $scenesResult = $sceneService->getScenes(
                $settings->ws_host,
                $settings->ws_port,
                $settings->ws_password,
                $settings->ws_secure,
            );

            if ($scenesResult['success']) {
                $this->obsScenes = $scenesResult['scenes'];
            } else {
                $this->discoveryError = 'Scenes: ' . ($scenesResult['error'] ?? 'unknown error');
            }

            $inputsResult = $sceneService->getInputs(
                $settings->ws_host,
                $settings->ws_port,
                $settings->ws_password,
                $settings->ws_secure,
            );

            if ($inputsResult['success']) {
                $this->obsInputs = $inputsResult['inputs'];
            } elseif (!$this->discoveryError) {
                $this->discoveryError = 'Inputs: ' . ($inputsResult['error'] ?? 'unknown error');
            }
        } catch (\Throwable $e) {
            $this->discoveryError = $e->getMessage();
            Log::error('OBS discovery failed', ['error' => $e->getMessage()]);
        } finally {
            $this->discovering = false;
        }
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    /**
     * Download hotkeys as a JSON file (stream response via Livewire).
     */
    public function exportHotkeys(HotkeyExporter $exporter): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $json     = $exporter->export();
        $filename = 'hotcue-hotkeys-' . now()->format('Ymd-His') . '.json';

        return response()->streamDownload(function () use ($json) {
            echo $json;
        }, $filename, ['Content-Type' => 'application/json']);
    }

    /**
     * Import hotkeys from uploaded JSON file.
     */
    public function importHotkeys(HotkeyExporter $exporter): void
    {
        $this->validate(['importFile' => 'required|file|mimes:json,txt|max:512']);

        $json = file_get_contents($this->importFile->getRealPath());
        $result = $exporter->import($json, merge: true);

        if (!empty($result['errors'])) {
            $this->importResult = '⚠ Imported ' . $result['imported'] . ', skipped ' . $result['skipped'] . '. Errors: ' . implode('; ', $result['errors']);
        } else {
            $this->importResult = '✓ Imported ' . $result['imported'] . ' hotkey(s) successfully.';
        }

        $this->importFile = null;
        $this->syncHotkeyRegistry();
        $this->loadHotkeys();
    }

    /**
     * Test the action of a hotkey using an isolated OBS connection.
     */
    public function testAction(int $id, \App\Services\Obs\ObsDiagnosticsService $diagnostics): void
    {
        $hotkey = Hotkey::query()->with('actions')->find($id);
        if (!$hotkey) {
            return;
        }

        $action = $hotkey->actions->first();
        if (!$action) {
            $this->testResult = "✗ No action configured for this hotkey.";
            return;
        }

        $this->testing = true;
        $this->testResult = null;

        $settings = Setting::instance();

        try {
            $result = $diagnostics->runAction(
                $settings->ws_host,
                $settings->ws_port,
                $settings->ws_password,
                $settings->ws_secure,
                $action->type,
                $action->payload ?? [],
            );

            $this->testResult = $result['success']
                ? "✓ Action '{$action->type}' executed successfully."
                : "✗ Failed: " . ($result['error'] ?? 'Unknown error');
        } catch (\Throwable $e) {
            $this->testResult = "✗ Error: " . $e->getMessage();
        } finally {
            $this->testing = false;
        }
    }

    /**
     * Validate action payload inline (avoids injecting ObsActionRunner from ReactPHP layer).
     */
    private function validateActionPayload(string $type, array $payload): array
    {
        // No-payload action types — always valid
        $noPayloadTypes = [
            'start_streaming', 'stop_streaming', 'toggle_streaming',
            'start_recording', 'stop_recording', 'toggle_recording',
            'pause_recording', 'resume_recording',
        ];
        if (in_array($type, $noPayloadTypes, true)) {
            return [];
        }

        return match ($type) {
            'switch_scene'     => empty($payload['scene']) ? ['Scene name is required'] : [],
            'toggle_mute'      => empty($payload['input']) ? ['Input name is required'] : [],
            'set_mute'         => array_values(array_filter([
                empty($payload['input']) ? 'Input name is required' : null,
                !isset($payload['muted']) ? 'Muted value is required' : null,
            ])),
            'adjust_volume_db' => array_values(array_filter([
                empty($payload['input']) ? 'Input name is required' : null,
                !isset($payload['deltaDb']) || !is_numeric($payload['deltaDb']) ? 'Delta dB must be numeric' : null,
            ])),
            'set_volume_db'    => array_values(array_filter([
                empty($payload['input']) ? 'Input name is required' : null,
                !isset($payload['db']) || !is_numeric($payload['db']) ? 'dB value must be numeric' : null,
            ])),
            default => ["Unknown action type: {$type}"],
        };
    }

    private function syncHotkeyRegistry(): void
    {
        if (!$this->isNativeContext()) {
            return;
        }
        try {
            app(\App\Services\Hotkeys\HotkeyRegistry::class)->registerAll();
        } catch (\Throwable $e) {
            Log::warning('HotkeyRegistry sync failed', ['error' => $e->getMessage()]);
        }
    }

    private function unregisterHotkey(int $hotkeyId): void
    {
        if (!$this->isNativeContext()) {
            return;
        }
        try {
            app(\App\Services\Hotkeys\HotkeyRegistry::class)->unregister($hotkeyId);
        } catch (\Throwable $e) {
            Log::warning('HotkeyRegistry unregister failed', ['error' => $e->getMessage()]);
        }
    }

    private function isNativeContext(): bool
    {
        return defined('NATIVE_PHP_RUNNING') && class_exists(\Native\Desktop\Facades\GlobalShortcut::class);
    }

    private function resetForm(): void
    {
        $this->editingId      = null;
        $this->name           = '';
        $this->accelerator    = '';
        $this->enabled        = true;
        $this->actionType     = 'switch_scene';
        $this->actionPayload  = [];
        $this->actions        = [['type' => 'switch_scene', 'payload' => []]];
        $this->obsScenes      = [];
        $this->obsInputs      = [];
        $this->discoveryError = null;
        $this->testResult     = null;
        $this->testing        = false;
        $this->importResult   = null;
        $this->importFile     = null;
        $this->resetErrorBag();
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('livewire.settings.hotkeys-manager');
    }
}

