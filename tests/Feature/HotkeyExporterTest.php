<?php

namespace Tests\Feature;

use App\Models\Hotkey;
use App\Models\HotkeyAction;
use App\Models\Setting;
use App\Services\Hotkeys\HotkeyExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HotkeyExporterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::instance();
    }

    private function exporter(): HotkeyExporter
    {
        return new HotkeyExporter();
    }

    public function test_export_produces_valid_json(): void
    {
        $hotkey = Hotkey::create(['name' => 'Switch Scene', 'accelerator' => 'cmd+1', 'enabled' => true]);
        HotkeyAction::create(['hotkey_id' => $hotkey->id, 'type' => 'switch_scene', 'payload' => ['scene' => 'Gaming']]);

        $json = $this->exporter()->export();
        $data = json_decode($json, true);

        $this->assertIsArray($data);
        $this->assertEquals(1, $data['version']);
        $this->assertArrayHasKey('exported', $data);
        $this->assertCount(1, $data['hotkeys']);
        $this->assertEquals('Switch Scene', $data['hotkeys'][0]['name']);
        $this->assertEquals('cmd+1', $data['hotkeys'][0]['accelerator']);
        $this->assertEquals('switch_scene', $data['hotkeys'][0]['actions'][0]['type']);
    }

    public function test_export_empty_is_valid_json(): void
    {
        $json = $this->exporter()->export();
        $data = json_decode($json, true);
        $this->assertIsArray($data);
        $this->assertEmpty($data['hotkeys']);
    }

    public function test_import_creates_hotkeys(): void
    {
        $json = json_encode([
            'version' => 1,
            'hotkeys' => [
                [
                    'name'        => 'Mute Mic',
                    'accelerator' => 'cmd+m',
                    'enabled'     => true,
                    'actions'     => [['type' => 'toggle_mute', 'payload' => ['input' => 'Mic/Aux']]],
                ],
            ],
        ]);

        $result = $this->exporter()->import($json);

        $this->assertEquals(1, $result['imported']);
        $this->assertEquals(0, $result['skipped']);
        $this->assertEmpty($result['errors']);

        $hotkey = Hotkey::where('accelerator', 'cmd+m')->first();
        $this->assertNotNull($hotkey);
        $this->assertEquals('Mute Mic', $hotkey->name);
        $this->assertEquals('toggle_mute', $hotkey->actions->first()->type);
    }

    public function test_import_normalizes_accelerator(): void
    {
        $json = json_encode([
            'version' => 1,
            'hotkeys' => [
                ['name' => 'Test', 'accelerator' => 'SHIFT+CMD+1', 'enabled' => true, 'actions' => []],
            ],
        ]);

        $this->exporter()->import($json);

        $this->assertDatabaseHas('hotkeys', ['accelerator' => 'cmd+shift+1']);
    }

    public function test_import_replace_mode_clears_existing(): void
    {
        Hotkey::create(['name' => 'Old', 'accelerator' => 'cmd+9', 'enabled' => true]);

        $json = json_encode([
            'version' => 1,
            'hotkeys' => [
                ['name' => 'New', 'accelerator' => 'cmd+8', 'enabled' => true, 'actions' => []],
            ],
        ]);

        $this->exporter()->import($json, merge: false);

        $this->assertDatabaseMissing('hotkeys', ['name' => 'Old']);
        $this->assertDatabaseHas('hotkeys', ['name' => 'New']);
    }

    public function test_import_merge_mode_preserves_existing(): void
    {
        Hotkey::create(['name' => 'Existing', 'accelerator' => 'cmd+9', 'enabled' => true]);

        $json = json_encode([
            'version' => 1,
            'hotkeys' => [
                ['name' => 'Imported', 'accelerator' => 'cmd+8', 'enabled' => true, 'actions' => []],
            ],
        ]);

        $this->exporter()->import($json, merge: true);

        $this->assertDatabaseHas('hotkeys', ['name' => 'Existing']);
        $this->assertDatabaseHas('hotkeys', ['name' => 'Imported']);
    }

    public function test_import_returns_error_for_invalid_json(): void
    {
        $result = $this->exporter()->import('not json');

        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('Invalid JSON', $result['errors'][0]);
    }

    public function test_import_returns_error_for_missing_hotkeys_key(): void
    {
        $result = $this->exporter()->import(json_encode(['version' => 1]));

        $this->assertNotEmpty($result['errors']);
    }

    public function test_import_skips_rows_with_missing_name(): void
    {
        $json = json_encode([
            'version' => 1,
            'hotkeys' => [
                ['name' => '', 'accelerator' => 'cmd+1', 'enabled' => true, 'actions' => []],
            ],
        ]);

        $result = $this->exporter()->import($json);

        $this->assertEquals(0, $result['imported']);
        $this->assertEquals(1, $result['skipped']);
    }

    public function test_export_then_import_roundtrip(): void
    {
        $hotkey = Hotkey::create(['name' => 'Roundtrip', 'accelerator' => 'cmd+r', 'enabled' => false]);
        HotkeyAction::create(['hotkey_id' => $hotkey->id, 'type' => 'toggle_recording', 'payload' => []]);

        $json = $this->exporter()->export();

        // Clear and re-import
        Hotkey::query()->delete();
        $result = $this->exporter()->import($json, merge: false);

        $this->assertEquals(1, $result['imported']);
        $restored = Hotkey::where('name', 'Roundtrip')->first();
        $this->assertNotNull($restored);
        $this->assertFalse($restored->enabled);
        $this->assertEquals('toggle_recording', $restored->actions->first()->type);
    }
}

