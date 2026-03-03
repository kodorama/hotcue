<?php

namespace Tests\Feature;

use App\Livewire\Settings\ConnectionSettings;
use App\Models\Setting;
use App\Services\Obs\ObsDiagnosticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ConnectionSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Ensure singleton settings row exists
        Setting::instance();
    }

    public function test_renders_with_saved_settings(): void
    {
        $setting = Setting::instance();
        $setting->ws_host = '192.168.1.10';
        $setting->ws_port = 4455;
        $setting->ws_secure = false;
        $setting->auto_connect = true;
        $setting->save();

        Livewire::test(ConnectionSettings::class)
            ->assertSet('ws_host', '192.168.1.10')
            ->assertSet('ws_port', 4455)
            ->assertSet('ws_secure', false)
            ->assertSet('auto_connect', true)
            ->assertSet('ws_password', null); // password never pre-filled
    }

    public function test_save_persists_connection_settings(): void
    {
        Livewire::test(ConnectionSettings::class)
            ->set('ws_host', '10.0.0.5')
            ->set('ws_port', 4444)
            ->set('ws_secure', false)
            ->set('auto_connect', false)
            ->call('save')
            ->assertHasNoErrors();

        $settings = Setting::instance();
        $this->assertEquals('10.0.0.5', $settings->ws_host);
        $this->assertEquals(4444, $settings->ws_port);
        $this->assertFalse($settings->auto_connect);
    }

    public function test_save_validates_required_host(): void
    {
        Livewire::test(ConnectionSettings::class)
            ->set('ws_host', '')
            ->call('save')
            ->assertHasErrors(['ws_host' => 'required']);
    }

    public function test_save_validates_port_range(): void
    {
        Livewire::test(ConnectionSettings::class)
            ->set('ws_port', 99999)
            ->call('save')
            ->assertHasErrors(['ws_port']);
    }

    public function test_save_does_not_overwrite_password_when_blank(): void
    {
        // Set an initial password
        $setting = Setting::instance();
        $setting->ws_password = 'original-password';
        $setting->save();

        // Save with blank password field — should NOT overwrite
        Livewire::test(ConnectionSettings::class)
            ->set('ws_password', '')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEquals('original-password', Setting::instance()->ws_password);
    }

    public function test_save_updates_password_when_provided(): void
    {
        Livewire::test(ConnectionSettings::class)
            ->set('ws_host', '127.0.0.1')
            ->set('ws_port', 4455)
            ->set('ws_password', 'newpassword123')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEquals('newpassword123', Setting::instance()->ws_password);
    }

    public function test_obs_status_defaults_to_unknown_in_web_mode(): void
    {
        // In web mode (no NATIVE_PHP_RUNNING constant), status should be 'unknown'
        Livewire::test(ConnectionSettings::class)
            ->assertSet('obsStatus', 'unknown');
    }

    public function test_refresh_status_is_callable(): void
    {
        Livewire::test(ConnectionSettings::class)
            ->call('refreshStatus')
            ->assertSet('obsStatus', 'unknown'); // unknown in web mode
    }

    public function test_reconnect_is_safe_in_web_mode(): void
    {
        // Should be a no-op in web mode without throwing
        Livewire::test(ConnectionSettings::class)
            ->call('reconnect')
            ->assertSet('reconnecting', false);
    }

    public function test_test_connection_returns_success_result(): void
    {
        $mockDiagnostics = $this->createMock(ObsDiagnosticsService::class);
        $mockDiagnostics->method('testConnection')->willReturn([
            'success' => true,
            'version' => '30.1.0',
            'rpcVersion' => '1',
            'error' => null,
        ]);

        $this->app->instance(ObsDiagnosticsService::class, $mockDiagnostics);

        Livewire::test(ConnectionSettings::class)
            ->set('ws_host', '127.0.0.1')
            ->set('ws_port', 4455)
            ->call('testConnection')
            ->assertSet('testing', false)
            ->assertSee('✓');
    }

    public function test_test_connection_returns_failure_result(): void
    {
        $mockDiagnostics = $this->createMock(ObsDiagnosticsService::class);
        $mockDiagnostics->method('testConnection')->willReturn([
            'success' => false,
            'version' => null,
            'rpcVersion' => null,
            'error' => 'Connection refused',
        ]);

        $this->app->instance(ObsDiagnosticsService::class, $mockDiagnostics);

        Livewire::test(ConnectionSettings::class)
            ->set('ws_host', '127.0.0.1')
            ->set('ws_port', 4455)
            ->call('testConnection')
            ->assertSet('testing', false)
            ->assertSee('✗');
    }
}

