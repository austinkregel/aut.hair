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
     *
     * @param  object  $event
     * @return void
     */
    public function handle($event)
    {
        activity()
            ->event(get_class($event))
            ->on($event->user)
            ->causedBy(auth()->user() ?? null)
            ->log('authenticated');
    }
}
