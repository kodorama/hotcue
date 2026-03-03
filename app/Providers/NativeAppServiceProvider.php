<?php

namespace App\Providers;

use App\Events\SettingsPageEvent;
use App\Models\Setting;
use App\Services\Hotkeys\HotkeyRegistry;
use App\Services\Obs\ObsConnectionManager;
use Illuminate\Support\Facades\Log;
use Native\Desktop\Facades\Menu;
use Native\Desktop\Facades\MenuBar;

class NativeAppServiceProvider
{
    public function boot(): void
    {
        $this->setupMenuBar();

        // On app boot, register hotkeys
        $this->registerHotkeys();

        // Subscribe to OBS events before auto-connecting
        $this->subscribeObsEvents();

        // Auto-connect to OBS if enabled
        $this->autoConnectObs();

        Log::info('NativePHP app booted');
    }

    private function setupMenuBar(): void
    {
        MenuBar::create()
            ->icon(storage_path('app/menuBarIconTemplate.png'))
            ->tooltip('HotCue — OBS Controller')
            ->onlyShowContextMenu()
            ->withContextMenu(
                Menu::make(
                    Menu::label('HotCue'),
                    Menu::separator(),
                    Menu::label('Settings…')->event(SettingsPageEvent::class),
                    Menu::separator(),
                    Menu::quit('Quit HotCue'),
                )
            );
    }

    private function subscribeObsEvents(): void
    {
        try {
            $manager = app()->make(ObsConnectionManager::class);

            $manager->onEvent('CurrentProgramSceneChanged', function (array $data) {
                Log::info('OBS scene changed', ['scene' => $data['sceneName'] ?? '?']);
            });

            $manager->onEvent('InputMuteStateChanged', function (array $data) {
                Log::info('OBS mute changed', [
                    'input' => $data['inputName'] ?? '?',
                    'muted' => $data['inputMuted'] ?? '?',
                ]);
            });

            $manager->onEvent('InputVolumeChanged', function (array $data) {
                Log::debug('OBS volume changed', [
                    'input' => $data['inputName'] ?? '?',
                    'db'    => $data['inputVolumeDb'] ?? '?',
                ]);
            });

            $manager->onEvent('StreamStateChanged', function (array $data) {
                Log::info('OBS stream state', ['active' => $data['outputActive'] ?? '?']);
            });

            $manager->onEvent('RecordStateChanged', function (array $data) {
                Log::info('OBS record state', ['active' => $data['outputActive'] ?? '?']);
            });
        } catch (\Throwable $e) {
            Log::error('Failed to subscribe OBS events', ['error' => $e->getMessage()]);
        }
    }

    private function registerHotkeys(): void
    {
        try {
            $registry = app()->make(HotkeyRegistry::class);
            $registry->registerAll();
            Log::info('Hotkeys registered on boot');
        } catch (\Throwable $e) {
            Log::error('Failed to register hotkeys on boot', ['error' => $e->getMessage()]);
        }
    }

    private function autoConnectObs(): void
    {
        try {
            $settings = Setting::instance();
            if ($settings->auto_connect) {
                $manager = app()->make(ObsConnectionManager::class);
                $manager->connect()
                    ->then(fn() => Log::info('Auto-connected to OBS'))
                    ->catch(function (\Throwable $e) use ($manager) {
                        Log::warning('Auto-connect failed, scheduling reconnect', ['error' => $e->getMessage()]);
                        $manager->scheduleReconnect();
                    });
            }
        } catch (\Throwable $e) {
            Log::error('Auto-connect exception', ['error' => $e->getMessage()]);
        }
    }
}

