<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Laravel\Passport\Events\AccessTokenCreated;
use function Psy\debug;

class LogAuthenticatedUserApprovedApplication
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
    public function handle(AccessTokenCreated $event)
    {
        dd($event, debug_backtrace());
        activity()
            ->on($event->user)
            ->causedBy(auth()->user() ?? null)
            ->log('authenticated');

    }
}
