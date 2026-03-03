<?php

namespace App\Providers;

use App\Services\Obs\ObsConnectionManager;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register ReactPHP event loop as singleton (only when NativePHP is active)
        $this->app->singleton(\React\EventLoop\LoopInterface::class, function (Application $app) {
            // Use StreamSelectLoop directly — do NOT use Factory::create() which calls Loop::set()
            // and would create a second global loop reference.
            $loop = new \React\EventLoop\StreamSelectLoop();
            \React\EventLoop\Loop::set($loop);
            return $loop;
        });

        // Register OBS connection manager as singleton
        $this->app->singleton(ObsConnectionManager::class, function (Application $app) {
            return new ObsConnectionManager($app->make(\React\EventLoop\LoopInterface::class));
        });

        // Register HotkeyDispatcher and HotkeyRegistry as singletons so
        // the in-memory $registeredHotkeys state is preserved across IoC resolutions.
        $this->app->singleton(\App\Services\Obs\ObsActionRunner::class, function (Application $app) {
            return new \App\Services\Obs\ObsActionRunner($app->make(ObsConnectionManager::class));
        });
        $this->app->singleton(\App\Services\Obs\ObsHotkeyRunner::class, function () {
            return new \App\Services\Obs\ObsHotkeyRunner();
        });
        $this->app->singleton(\App\Services\Hotkeys\HotkeyDispatcher::class, function (Application $app) {
            return new \App\Services\Hotkeys\HotkeyDispatcher($app->make(\App\Services\Obs\ObsHotkeyRunner::class));
        });
        // HotkeyRegistry is state-bearing (tracks which hotkeys are registered) so must be a singleton.
        // It no longer receives HotkeyDispatcher directly; dispatch goes through HandleHotkeyPressed listener.
        $this->app->singleton(\App\Services\Hotkeys\HotkeyRegistry::class, function (Application $app) {
            return new \App\Services\Hotkeys\HotkeyRegistry();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {

        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null
        );
    }
}
