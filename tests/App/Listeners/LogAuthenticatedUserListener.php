<?php

namespace Tests\App\Listeners;

class LogAuthenticatedUserListener
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(object $event): void
    {
        activity()
            ->event(get_class($event))
            ->on($event->user)
            ->causedBy(auth()->user() ?? null)
            ->log('authenticated');
    }
}
