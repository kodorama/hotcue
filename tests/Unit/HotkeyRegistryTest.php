<?php

namespace Tests\Unit;

use App\Events\HotkeyPressed;
use App\Models\Hotkey;
use App\Services\Hotkeys\HotkeyRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Native\Desktop\Facades\GlobalShortcut;
use Tests\TestCase;

class HotkeyRegistryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        GlobalShortcut::fake();
    }

    public function test_register_all_registers_enabled_hotkeys(): void
    {
        Hotkey::create(['name' => 'Hotkey A', 'accelerator' => 'cmd+shift+a', 'enabled' => true]);
        Hotkey::create(['name' => 'Hotkey B', 'accelerator' => 'cmd+shift+b', 'enabled' => true]);
        Hotkey::create(['name' => 'Disabled', 'accelerator' => 'cmd+shift+d', 'enabled' => false]);

        $registry = new HotkeyRegistry();
        $registry->registerAll();

        GlobalShortcut::assertRegisteredCount(2);
    }

    public function test_register_passes_hotkey_pressed_event_class(): void
    {
        Hotkey::create(['name' => 'Test', 'accelerator' => 'cmd+shift+t', 'enabled' => true]);

        $registry = new HotkeyRegistry();
        $registry->registerAll();

        GlobalShortcut::assertEvent(HotkeyPressed::class);
    }

    public function test_register_converts_accelerator_to_native_format(): void
    {
        Hotkey::create(['name' => 'Test', 'accelerator' => 'cmd+shift+a', 'enabled' => true]);

        $registry = new HotkeyRegistry();
        $registry->registerAll();

        // DB stores 'cmd+shift+a', NativePHP expects 'Cmd+Shift+A'
        GlobalShortcut::assertKey('Cmd+Shift+A');
    }

    public function test_register_all_unregisters_before_re_registering(): void
    {
        Hotkey::create(['name' => 'Test', 'accelerator' => 'cmd+shift+r', 'enabled' => true]);

        $registry = new HotkeyRegistry();
        $registry->registerAll(); // 1st call: unregisterAll hits DB (1 stale key), registers 1

        $registry->registerAll(); // 2nd call: unregisterAll hits in-memory (1) + DB scan (1 stale) = 2, registers 1

        // Total unregistrations: 1 (first call DB scan) + 1 (second call in-memory) + 1 (second call DB scan) = 3
        GlobalShortcut::assertUnregisteredCount(3);
        // Registered twice total (once per registerAll)
        GlobalShortcut::assertRegisteredCount(2);
    }

    public function test_unregister_all_on_fresh_instance_cleans_db_hotkeys(): void
    {
        // Simulate a fresh PHP process: registry has no in-memory state,
        // but Electron may still hold registrations from a previous boot.
        Hotkey::create(['name' => 'Stale A', 'accelerator' => 'cmd+shift+s', 'enabled' => true]);
        Hotkey::create(['name' => 'Stale B', 'accelerator' => 'cmd+shift+b', 'enabled' => true]);

        $freshRegistry = new HotkeyRegistry(); // empty $registeredHotkeys
        $freshRegistry->unregisterAll();

        // Should have unregistered both DB-enabled hotkeys even with no in-memory state
        GlobalShortcut::assertUnregisteredCount(2);
    }

    public function test_unregister_removes_single_hotkey(): void
    {
        $hotkey = Hotkey::create(['name' => 'Test', 'accelerator' => 'cmd+shift+u', 'enabled' => true]);

        $registry = new HotkeyRegistry();
        $registry->register($hotkey);
        $registry->unregister($hotkey->id);

        GlobalShortcut::assertUnregisteredCount(1);
    }

    public function test_unregister_noop_for_unknown_id(): void
    {
        $registry = new HotkeyRegistry();
        $registry->unregister(99999); // Should not throw

        GlobalShortcut::assertUnregisteredCount(0);
    }

    public function test_is_accelerator_registered_returns_true_for_enabled(): void
    {
        Hotkey::create(['name' => 'Existing', 'accelerator' => 'cmd+shift+x', 'enabled' => true]);

        $registry = new HotkeyRegistry();
        $this->assertTrue($registry->isAcceleratorRegistered('cmd+shift+x'));
    }

    public function test_is_accelerator_registered_returns_false_for_disabled(): void
    {
        Hotkey::create(['name' => 'Disabled', 'accelerator' => 'cmd+shift+y', 'enabled' => false]);

        $registry = new HotkeyRegistry();
        $this->assertFalse($registry->isAcceleratorRegistered('cmd+shift+y'));
    }

    public function test_is_accelerator_registered_excludes_given_id(): void
    {
        $hotkey = Hotkey::create(['name' => 'Self', 'accelerator' => 'cmd+shift+z', 'enabled' => true]);

        $registry = new HotkeyRegistry();
        $this->assertFalse($registry->isAcceleratorRegistered('cmd+shift+z', $hotkey->id));
    }
}

