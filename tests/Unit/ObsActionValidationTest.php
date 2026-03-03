<?php

namespace Tests\Unit;

use App\Services\Obs\ObsActionRunner;
use App\Services\Obs\ObsConnectionManager;
use Mockery;
use Tests\TestCase;

class ObsActionValidationTest extends TestCase
{
    private ObsActionRunner $actionRunner;

    protected function setUp(): void
    {
        parent::setUp();

        $mockManager = Mockery::mock(ObsConnectionManager::class);
        $this->actionRunner = new ObsActionRunner($mockManager);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_switch_scene_validation_success(): void
    {
        $errors = $this->actionRunner->validatePayload('switch_scene', [
            'scene' => 'Gaming Scene',
        ]);

        $this->assertEmpty($errors);
    }

    public function test_switch_scene_validation_missing_scene(): void
    {
        $errors = $this->actionRunner->validatePayload('switch_scene', []);

        $this->assertNotEmpty($errors);
        $this->assertContains('Scene name is required', $errors);
    }

    public function test_toggle_mute_validation_success(): void
    {
        $errors = $this->actionRunner->validatePayload('toggle_mute', [
            'input' => 'Microphone',
        ]);

        $this->assertEmpty($errors);
    }

    public function test_toggle_mute_validation_missing_input(): void
    {
        $errors = $this->actionRunner->validatePayload('toggle_mute', []);

        $this->assertNotEmpty($errors);
        $this->assertContains('Input name is required', $errors);
    }

    public function test_set_mute_validation_success(): void
    {
        $errors = $this->actionRunner->validatePayload('set_mute', [
            'input' => 'Desktop Audio',
            'muted' => true,
        ]);

        $this->assertEmpty($errors);
    }

    public function test_set_mute_validation_missing_muted(): void
    {
        $errors = $this->actionRunner->validatePayload('set_mute', [
            'input' => 'Desktop Audio',
        ]);

        $this->assertNotEmpty($errors);
        $this->assertContains('Muted must be a boolean', $errors);
    }

    public function test_adjust_volume_db_validation_success(): void
    {
        $errors = $this->actionRunner->validatePayload('adjust_volume_db', [
            'input' => 'Desktop Audio',
            'deltaDb' => -2.5,
        ]);

        $this->assertEmpty($errors);
    }

    public function test_adjust_volume_db_validation_missing_delta(): void
    {
        $errors = $this->actionRunner->validatePayload('adjust_volume_db', [
            'input' => 'Desktop Audio',
        ]);

        $this->assertNotEmpty($errors);
        $this->assertContains('Delta dB must be numeric', $errors);
    }

    public function test_set_volume_db_validation_success(): void
    {
        $errors = $this->actionRunner->validatePayload('set_volume_db', [
            'input' => 'Desktop Audio',
            'db' => -10.0,
        ]);

        $this->assertEmpty($errors);
    }

    public function test_set_volume_db_validation_missing_db(): void
    {
        $errors = $this->actionRunner->validatePayload('set_volume_db', [
            'input' => 'Desktop Audio',
        ]);

        $this->assertNotEmpty($errors);
        $this->assertContains('dB value must be numeric', $errors);
    }

    public function test_unknown_action_type(): void
    {
        $errors = $this->actionRunner->validatePayload('unknown_action', []);

        $this->assertNotEmpty($errors);
        $this->assertContains('Unknown action type: unknown_action', $errors);
    }

    public function test_start_streaming_needs_no_payload(): void
    {
        $this->assertEmpty($this->actionRunner->validatePayload('start_streaming', []));
    }

    public function test_stop_streaming_needs_no_payload(): void
    {
        $this->assertEmpty($this->actionRunner->validatePayload('stop_streaming', []));
    }

    public function test_toggle_streaming_needs_no_payload(): void
    {
        $this->assertEmpty($this->actionRunner->validatePayload('toggle_streaming', []));
    }

    public function test_start_recording_needs_no_payload(): void
    {
        $this->assertEmpty($this->actionRunner->validatePayload('start_recording', []));
    }

    public function test_stop_recording_needs_no_payload(): void
    {
        $this->assertEmpty($this->actionRunner->validatePayload('stop_recording', []));
    }

    public function test_toggle_recording_needs_no_payload(): void
    {
        $this->assertEmpty($this->actionRunner->validatePayload('toggle_recording', []));
    }

    public function test_pause_recording_needs_no_payload(): void
    {
        $this->assertEmpty($this->actionRunner->validatePayload('pause_recording', []));
    }

    public function test_resume_recording_needs_no_payload(): void
    {
        $this->assertEmpty($this->actionRunner->validatePayload('resume_recording', []));
    }
}
