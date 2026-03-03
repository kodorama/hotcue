<?php

namespace Tests\Unit;

use App\Models\Hotkey;
use App\Models\HotkeyAction;
use App\Services\Hotkeys\HotkeyDispatcher;
use App\Services\Obs\ObsHotkeyRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

class HotkeyDispatcherTest extends TestCase
{
    use RefreshDatabase;

    private ObsHotkeyRunner $runner;
    private HotkeyDispatcher $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->runner     = Mockery::mock(ObsHotkeyRunner::class);
        $this->dispatcher = new HotkeyDispatcher($this->runner);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_dispatch_calls_runner_for_enabled_hotkey(): void
    {
        $hotkey = Hotkey::create([
            'name'        => 'Test Hotkey',
            'accelerator' => 'cmd+shift+t',
            'enabled'     => true,
        ]);
        HotkeyAction::create([
            'hotkey_id' => $hotkey->id,
            'type'      => 'switch_scene',
            'payload'   => ['scene' => 'Gaming'],
        ]);

        $called = false;
        $this->runner
            ->shouldReceive('runActions')
            ->once()
            ->andReturnUsing(function (Collection $actions, string $name) use (&$called) {
                $called = true;
                $this->assertSame('Test Hotkey', $name);
                $this->assertCount(1, $actions);
                $this->assertSame('switch_scene', $actions->first()->type);
            });

        $this->dispatcher->dispatch($hotkey->id);

        $this->assertTrue($called, 'ObsHotkeyRunner::runActions should have been called');
    }

    public function test_dispatch_passes_all_actions_to_runner(): void
    {
        $hotkey = Hotkey::create([
            'name' => 'Multi', 'accelerator' => 'cmd+shift+m', 'enabled' => true,
        ]);
        HotkeyAction::create(['hotkey_id' => $hotkey->id, 'type' => 'switch_scene', 'payload' => ['scene' => 'A']]);
        HotkeyAction::create(['hotkey_id' => $hotkey->id, 'type' => 'toggle_mute',  'payload' => ['input' => 'Mic']]);

        $receivedCount = 0;
        $this->runner
            ->shouldReceive('runActions')
            ->once()
            ->andReturnUsing(function (Collection $actions) use (&$receivedCount) {
                $receivedCount = $actions->count();
            });

        $this->dispatcher->dispatch($hotkey->id);

        $this->assertSame(2, $receivedCount, 'All actions should be passed to the runner');
    }

    public function test_dispatch_skips_disabled_hotkey(): void
    {
        $hotkey = Hotkey::create([
            'name' => 'Disabled', 'accelerator' => 'cmd+shift+d', 'enabled' => false,
        ]);
        HotkeyAction::create(['hotkey_id' => $hotkey->id, 'type' => 'switch_scene', 'payload' => ['scene' => 'X']]);

        $this->runner->shouldNotReceive('runActions');

        $this->dispatcher->dispatch($hotkey->id);

        $this->assertTrue(true, 'Runner must not be called for a disabled hotkey');
    }

    public function test_dispatch_skips_nonexistent_hotkey(): void
    {
        $this->runner->shouldNotReceive('runActions');

        $this->dispatcher->dispatch(99999);

        $this->assertTrue(true, 'Runner must not be called for a missing hotkey');
    }

    public function test_dispatch_selects_correct_hotkey_name(): void
    {
        $hotkey = Hotkey::create([
            'name' => 'Mute Mic', 'accelerator' => 'cmd+shift+q', 'enabled' => true,
        ]);
        HotkeyAction::create(['hotkey_id' => $hotkey->id, 'type' => 'toggle_mute', 'payload' => ['input' => 'Mic/Aux']]);

        $receivedName = null;
        $this->runner
            ->shouldReceive('runActions')
            ->once()
            ->andReturnUsing(function (Collection $actions, string $name) use (&$receivedName) {
                $receivedName = $name;
            });

        $this->dispatcher->dispatch($hotkey->id);

        $this->assertSame('Mute Mic', $receivedName);
    }
}
