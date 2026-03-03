<?php

namespace App\Listeners;

use App\Events\SettingsPageEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Native\Desktop\Facades\Window;

class OpenSettingsWindow
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(SettingsPageEvent $event): void
    {
        Window::open('settings')
            ->route('settings')
            ->title('Settings…')
            ->skipTaskbar()
            ->suppressNewWindows()
            ->minWidth(800)
            ->minHeight(800);
    }
}
