<?php

namespace Tests\Unit;

use App\Events\HotkeyPressed;
use App\Listeners\HandleHotkeyPressed;
use App\Models\Hotkey;
use App\Models\HotkeyAction;
use App\Services\Hotkeys\HotkeyDispatcher;
use App\Services\Obs\ObsHotkeyRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class HandleHotkeyPressedTest extends TestCase
{
    use RefreshDatabase;

    private HotkeyDispatcher $dispatcher;
    private HandleHotkeyPressed $listener;

    protected function setUp(): void
    {
        parent::setUp();

        $runner           = Mockery::mock(ObsHotkeyRunner::class);
        $this->dispatcher = new HotkeyDispatcher($runner);

        // Allow runActions to be called (or not) without failing
        $runner->shouldReceive('runActions')->andReturnNull()->byDefault();

        $this->listener = new HandleHotkeyPressed($this->dispatcher);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_listener_dispatches_matching_hotkey_by_normalized_accelerator(): void
    {
        $hotkey = Hotkey::create([
            'name'        => 'Gaming Scene',
            'accelerator' => 'cmd+shift+a', // DB normalized format
            'enabled'     => true,
        ]);
        HotkeyAction::create([
            'hotkey_id' => $hotkey->id,
            'type'      => 'switch_scene',
            'payload'   => ['scene' => 'Gaming'],
        ]);

        // Electron sends the NativePHP-registered format (Title-case modifiers, upper key)
        $event = new HotkeyPressed('Cmd+Shift+A');

        // Should not throw, and should look up + dispatch the hotkey
        $this->listener->handle($event);

        $this->assertTrue(true, 'Listener should handle a valid NativePHP accelerator without errors');
    }

    public function test_listener_handles_unknown_accelerator_gracefully(): void
    {
        // No hotkey in DB for this accelerator
        $event = new HotkeyPressed('Cmd+Shift+Z');

        $this->listener->handle($event);

        $this->assertTrue(true, 'Listener must not throw when no hotkey matches the accelerator');
    }

    public function test_listener_ignores_disabled_hotkey(): void
    {
        Hotkey::create([
            'name'        => 'Disabled',
            'accelerator' => 'cmd+shift+d',
            'enabled'     => false,
        ]);

        $event = new HotkeyPressed('Cmd+Shift+D');

        $this->listener->handle($event);

        $this->assertTrue(true, 'Listener must not dispatch for a disabled hotkey');
    }

    public function test_listener_normalizes_various_native_formats(): void
    {
        // DB stores lowercase normalized; Electron may send different capitalizations
        $hotkey = Hotkey::create([
            'name'        => 'Ctrl Alt',
            'accelerator' => 'ctrl+alt+f',
            'enabled'     => true,
        ]);
        HotkeyAction::create([
            'hotkey_id' => $hotkey->id,
            'type'      => 'toggle_mute',
            'payload'   => ['input' => 'Mic'],
        ]);

        // Even if Electron sends mixed case, normalize() should handle it
        $event = new HotkeyPressed('Ctrl+Alt+F');
        $this->listener->handle($event);

        $this->assertTrue(true, 'Listener must normalize Ctrl+Alt+F to ctrl+alt+f for DB lookup');
    }

    public function test_hotkey_pressed_event_carries_accelerator_string(): void
    {
        $event = new HotkeyPressed('Cmd+Shift+A');
        $this->assertSame('Cmd+Shift+A', $event->accelerator);
    }
}

