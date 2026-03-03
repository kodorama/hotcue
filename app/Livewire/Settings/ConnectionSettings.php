<?php

namespace App\Livewire\Settings;

use App\Models\Setting;
use App\Services\Obs\ObsConnectionManager;
use App\Services\Obs\ObsDiagnosticsService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class ConnectionSettings extends Component
{
    public bool $testing = false;
    public ?string $testResult = null;
    public string $ws_host = '127.0.0.1';
    public int $ws_port = 4455;
    public bool $ws_secure = false;
    public ?string $ws_password = null;
    public bool $auto_connect = true;

    /** 'connected' | 'disconnected' | 'unknown' */
    public string $obsStatus = 'unknown';
    public bool $reconnecting = false;

    public function mount(): void
    {
        $settings = Setting::instance();
        $this->ws_host = $settings->ws_host ?? '127.0.0.1';
        $this->ws_port = $settings->ws_port ?? 4455;
        $this->ws_secure = (bool) ($settings->ws_secure ?? false);
        $this->ws_password = null; // Never pre-fill password field
        $this->auto_connect = (bool) ($settings->auto_connect ?? true);

        $this->refreshStatus();
    }

    /**
     * Refresh OBS connection status from the manager singleton.
     * Safe to call in both web and NativePHP contexts.
     */
    public function refreshStatus(): void
    {
        if (!$this->isNativeContext()) {
            $this->obsStatus = 'unknown';
            return;
        }

        try {
            $manager = app(ObsConnectionManager::class);
            $this->obsStatus = $manager->isConnected() ? 'connected' : 'disconnected';
        } catch (\Throwable $e) {
            $this->obsStatus = 'unknown';
        }
    }

    /**
     * Trigger a manual reconnect via ObsConnectionManager.
     */
    public function reconnect(): void
    {
        if (!$this->isNativeContext()) {
            return;
        }

        $this->reconnecting = true;

        try {
            $manager = app(ObsConnectionManager::class);
            $manager->connect()
                ->then(function () {
                    Log::info('Manual reconnect succeeded');
                })
                ->catch(function (\Throwable $e) use ($manager) {
                    Log::warning('Manual reconnect failed', ['error' => $e->getMessage()]);
                    $manager->scheduleReconnect();
                });
        } catch (\Throwable $e) {
            Log::error('Reconnect exception', ['error' => $e->getMessage()]);
        } finally {
            $this->reconnecting = false;
            $this->refreshStatus();
        }
    }

    public function save(): void
    {
        $this->validate([
            'ws_host' => 'required|string|max:255',
            'ws_port' => 'required|integer|min:1|max:65535',
            'ws_secure' => 'boolean',
            'auto_connect' => 'boolean',
        ]);

        $settings = Setting::instance();
        $settings->ws_host = $this->ws_host;
        $settings->ws_port = (int) $this->ws_port;
        $settings->ws_secure = $this->ws_secure;
        $settings->auto_connect = $this->auto_connect;

        // Only update password if a new one was provided
        if (!empty($this->ws_password)) {
            $settings->ws_password = $this->ws_password;
        }

        $settings->save();

        // Clear the password field after save
        $this->ws_password = null;

        session()->flash('saved', 'Settings saved successfully.');
    }

    public function testConnection(ObsDiagnosticsService $diagnostics): void
    {
        $this->testing = true;
        $this->testResult = null;

        try {
            // Use the current form values, not saved settings
            $password = $this->ws_password ?: Setting::instance()->ws_password;

            $result = $diagnostics->testConnection(
                $this->ws_host,
                (int) $this->ws_port,
                $password,
                $this->ws_secure,
            );

            if ($result['success']) {
                $this->testResult = "✓ Connected! OBS {$result['version']} (WebSocket {$result['rpcVersion']})";
            } else {
                $this->testResult = "✗ Failed: " . ($result['error'] ?? 'Unknown error');
            }
        } catch (\Throwable $e) {
            $this->testResult = "✗ Error: " . $e->getMessage();
            Log::error('Connection test exception', ['error' => $e->getMessage()]);
        } finally {
            $this->testing = false;
            $this->refreshStatus();
        }
    }

    private function isNativeContext(): bool
    {
        return defined('NATIVE_PHP_RUNNING')
            && app()->bound(ObsConnectionManager::class);
    }

    public function render(): View
    {
        return view('livewire.settings.connection-settings');
    }
}
