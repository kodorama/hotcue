<?php

namespace Tests\Feature;

use App\Livewire\Settings\HotkeysManager;
use App\Models\Hotkey;
use App\Models\HotkeyAction;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HotkeysManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::instance(); // ensure singleton row exists
    }

    public function test_renders_empty_state(): void
    {
        Livewire::test(HotkeysManager::class)
            ->assertSee('No hotkeys configured');
    }

    public function test_renders_existing_hotkeys(): void
    {
        $hotkey = Hotkey::create(['name' => 'Switch to Gaming', 'accelerator' => 'cmd+shift+1', 'enabled' => true]);
        HotkeyAction::create(['hotkey_id' => $hotkey->id, 'type' => 'switch_scene', 'payload' => ['scene' => 'Gaming']]);

        Livewire::test(HotkeysManager::class)
            ->assertSee('Switch to Gaming')
            ->assertSee('cmd+shift+1');
    }

    public function test_create_opens_modal(): void
    {
        Livewire::test(HotkeysManager::class)
            ->call('create')
            ->assertSet('showModal', true)
            ->assertSet('editingId', null);
    }

    public function test_save_creates_new_hotkey(): void
    {
        Livewire::test(HotkeysManager::class)
            ->set('name', 'Mute Mic')
            ->set('accelerator', 'cmd+m')
            ->set('enabled', true)
            ->set('actionType', 'toggle_mute')
            ->set('actionPayload', ['input' => 'Mic/Aux'])
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showModal', false);

        $hotkey = Hotkey::where('name', 'Mute Mic')->first();
        $this->assertNotNull($hotkey);
        $this->assertEquals('cmd+m', $hotkey->accelerator);
        $this->assertTrue($hotkey->enabled);

        $action = $hotkey->actions->first();
        $this->assertNotNull($action);
        $this->assertEquals('toggle_mute', $action->type);
        $this->assertEquals(['input' => 'Mic/Aux'], $action->payload);
    }

    public function test_save_normalizes_accelerator(): void
    {
        Livewire::test(HotkeysManager::class)
            ->set('name', 'Test')
            ->set('accelerator', 'Shift+CMD+1')  // unnormalized
            ->set('actionType', 'switch_scene')
            ->set('actionPayload', ['scene' => 'Game'])
            ->call('save')
            ->assertHasNoErrors();

        $hotkey = Hotkey::where('name', 'Test')->first();
        $this->assertEquals('cmd+shift+1', $hotkey->accelerator); // normalized
    }

    public function test_save_validates_required_name(): void
    {
        Livewire::test(HotkeysManager::class)
            ->set('name', '')
            ->set('accelerator', 'cmd+1')
            ->call('save')
            ->assertHasErrors(['name' => 'required']);
    }

    public function test_save_validates_required_accelerator(): void
    {
        Livewire::test(HotkeysManager::class)
            ->set('name', 'My Hotkey')
            ->set('accelerator', '')
            ->call('save')
            ->assertHasErrors(['accelerator' => 'required']);
    }

    public function test_save_validates_switch_scene_payload(): void
    {
        Livewire::test(HotkeysManager::class)
            ->set('name', 'Test')
            ->set('accelerator', 'cmd+1')
            ->set('actionType', 'switch_scene')
            ->set('actionPayload', []) // missing scene
            ->call('save')
            ->assertHasErrors(['actionPayload']);
    }

    public function test_save_rejects_duplicate_accelerator_for_enabled_hotkeys(): void
    {
        Hotkey::create(['name' => 'Existing', 'accelerator' => 'cmd+1', 'enabled' => true]);

        Livewire::test(HotkeysManager::class)
            ->set('name', 'New')
            ->set('accelerator', 'cmd+1') // duplicate
            ->set('enabled', true)
            ->set('actionType', 'toggle_mute')
            ->set('actionPayload', ['input' => 'Mic'])
            ->call('save')
            ->assertHasErrors(['accelerator']);
    }

    public function test_duplicate_accelerator_allowed_when_new_hotkey_disabled(): void
    {
        Hotkey::create(['name' => 'Existing', 'accelerator' => 'cmd+1', 'enabled' => true]);

        Livewire::test(HotkeysManager::class)
            ->set('name', 'New (disabled)')
            ->set('accelerator', 'cmd+1')
            ->set('enabled', false) // disabled — no conflict
            ->set('actionType', 'toggle_mute')
            ->set('actionPayload', ['input' => 'Mic'])
            ->call('save')
            ->assertHasNoErrors();
    }

    public function test_edit_loads_hotkey_into_form(): void
    {
        $hotkey = Hotkey::create(['name' => 'My Key', 'accelerator' => 'cmd+shift+2', 'enabled' => true]);
        HotkeyAction::create(['hotkey_id' => $hotkey->id, 'type' => 'toggle_mute', 'payload' => ['input' => 'Mic']]);

        Livewire::test(HotkeysManager::class)
            ->call('edit', $hotkey->id)
            ->assertSet('editingId', $hotkey->id)
            ->assertSet('name', 'My Key')
            ->assertSet('accelerator', 'cmd+shift+2')
            ->assertSet('actionType', 'toggle_mute')
            ->assertSet('showModal', true);
    }

    public function test_delete_removes_hotkey(): void
    {
        $hotkey = Hotkey::create(['name' => 'Delete Me', 'accelerator' => 'cmd+d', 'enabled' => true]);

        Livewire::test(HotkeysManager::class)
            ->call('delete', $hotkey->id);

        $this->assertDatabaseMissing('hotkeys', ['id' => $hotkey->id]);
    }

    public function test_toggle_enabled_flips_state(): void
    {
        $hotkey = Hotkey::create(['name' => 'Toggle Test', 'accelerator' => 'cmd+t', 'enabled' => true]);

        Livewire::test(HotkeysManager::class)
            ->call('toggleEnabled', $hotkey->id);

        $this->assertFalse(Hotkey::find($hotkey->id)->enabled);

        Livewire::test(HotkeysManager::class)
            ->call('toggleEnabled', $hotkey->id);

        $this->assertTrue(Hotkey::find($hotkey->id)->enabled);
    }

    public function test_close_modal_resets_form(): void
    {
        Livewire::test(HotkeysManager::class)
            ->set('name', 'Some name')
            ->set('showModal', true)
            ->call('closeModal')
            ->assertSet('showModal', false)
            ->assertSet('name', '')
            ->assertSet('editingId', null);
    }

    public function test_save_streaming_action_needs_no_payload(): void
    {
        Livewire::test(HotkeysManager::class)
            ->set('name', 'Go Live')
            ->set('accelerator', 'cmd+shift+s')
            ->set('enabled', true)
            ->set('actionType', 'start_streaming')
            ->set('actionPayload', [])
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showModal', false);

        $hotkey = Hotkey::where('name', 'Go Live')->first();
        $this->assertNotNull($hotkey);
        $this->assertEquals('start_streaming', $hotkey->actions->first()->type);
    }

    public function test_save_recording_action_needs_no_payload(): void
    {
        Livewire::test(HotkeysManager::class)
            ->set('name', 'Record')
            ->set('accelerator', 'cmd+shift+r')
            ->set('actionType', 'toggle_recording')
            ->set('actionPayload', [])
            ->call('save')
            ->assertHasNoErrors();
    }

    public function test_test_action_sets_result_when_no_action_configured(): void
    {
        $hotkey = Hotkey::create(['name' => 'No Action', 'accelerator' => 'cmd+n', 'enabled' => true]);
        // Deliberately no action created

        Livewire::test(HotkeysManager::class)
            ->call('testAction', $hotkey->id)
            ->assertSet('testing', false)
            ->assertSee('✗');
    }

    public function test_multiple_actions_are_saved_per_hotkey(): void
    {
        Livewire::test(HotkeysManager::class)
            ->set('name', 'Multi Action')
            ->set('accelerator', 'cmd+shift+m')
            ->set('actionType', 'toggle_mute')          // legacy field (first action)
            ->set('actionPayload', ['input' => 'Mic'])
            ->set('actions', [
                ['type' => 'switch_scene', 'payload' => ['scene' => 'Gaming']],
                ['type' => 'toggle_mute',  'payload' => ['input' => 'Mic/Aux']],
                ['type' => 'start_streaming', 'payload' => []],
            ])
            ->call('save')
            ->assertHasNoErrors();

        $hotkey = Hotkey::where('name', 'Multi Action')->first();
        $this->assertNotNull($hotkey);
        $this->assertCount(3, $hotkey->actions);
        $this->assertEquals('switch_scene',   $hotkey->actions[0]->type);
        $this->assertEquals('toggle_mute',    $hotkey->actions[1]->type);
        $this->assertEquals('start_streaming',$hotkey->actions[2]->type);
    }

    public function test_add_and_remove_action_methods(): void
    {
        $component = Livewire::test(HotkeysManager::class)
            ->call('create');

        // Should start with 1 action
        $component->assertCount('actions', 1);

        // Add two more
        $component->call('addAction')->call('addAction');
        $component->assertCount('actions', 3);

        // Remove middle one
        $component->call('removeAction', 1);
        $component->assertCount('actions', 2);

        // Cannot remove below 1
        $component->call('removeAction', 0);
        $component->assertCount('actions', 1);

        $component->call('removeAction', 0); // still 1, no-op
        $component->assertCount('actions', 1);
    }
}


