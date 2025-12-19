<?php

namespace App\Listeners;

use App\Models\User;
use Laravel\Passport\Client;
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
        $client = Client::find($event->clientId);

        $headerLogs = iterator_to_array(request()?->headers?->getIterator());

        unset($headerLogs['cookie']);
        unset($headerLogs['authorization']);
        unset($headerLogs['x-csrf-token']);
        unset($headerLogs['x-xsrf-token']);

        $log = activity();

        // client_credentials access tokens have no user context; keep logging but avoid null performedOn().
        if ($user) {
            $log->performedOn($user);
        } elseif ($client) {
            $log->performedOn($client);
        }

        $log
            ->causedByAnonymous()
            ->withProperty('ip', request()->ip())
            ->withProperty('headers', $headerLogs)
            ->withProperty('oauth_client_id', $event->clientId)
            ->log('approved');
    }
}
