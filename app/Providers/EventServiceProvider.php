<?php

namespace App\Providers;

use App\Events\HotkeyPressed;
use App\Listeners\HandleHotkeyPressed;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

/**
 * Explicit event→listener wiring.
 *
 * Using a dedicated EventServiceProvider (extends the framework's base) guarantees
 * that listener bindings are registered before any event is fired, regardless of
 * whether auto-discovery is enabled or whether AppServiceProvider::boot() has run
 * during a given request cycle.
 */
class EventServiceProvider extends ServiceProvider
{
    /**
     * The event → listener mappings.
     *
     * @var array<class-string, array<class-string>>
     */
    protected $listen = [
        HotkeyPressed::class => [
            HandleHotkeyPressed::class,
        ],
    ];

    /**
     * Determine if events and listeners should be automatically discovered.
     * Keep false — we declare everything explicitly to avoid double-registration.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}

