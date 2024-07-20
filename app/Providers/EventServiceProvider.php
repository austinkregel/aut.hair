<?php

namespace App\Providers;

use App\Events\ComposerActionLoggedToConsole;
use App\Listeners\SynologyExtendSocialiteListener;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        //
        // Do NOT add socialite providers here manually,
        //
        SocialiteWasCalled::class => [
            SynologyExtendSocialiteListener::class.'@handle',
        ],
        ComposerActionLoggedToConsole::class => [
            //
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     *
     * @return bool
     */
    public function shouldDiscoverEvents()
    {
        return false;
    }
}
