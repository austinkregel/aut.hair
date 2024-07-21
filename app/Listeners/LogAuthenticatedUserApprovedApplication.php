<?php

namespace App\Listeners;

use App\Models\User;
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
        $user = User::find($event->userId);


        $headerLogs = iterator_to_array(request()?->headers?->getIterator());

        unset($headerLogs['cookie']);
        unset($headerLogs['authorization']);
        unset($headerLogs['x-csrf-token']);
        unset($headerLogs['x-xsrf-token']);

        activity()
            ->performedOn($user)
            ->causedByAnonymous()
            ->withProperty('ip', request()->ip())
            ->withProperty('headers', $headerLogs)
            ->withProperty('oauth_client_id', $event->clientId)
            ->log('approved');
    }
}
