<?php

namespace App\Listeners;

use App\Models\User;
use Laravel\Passport\Events\AccessTokenCreated;

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
     */
    public function handle(AccessTokenCreated $event): void
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
