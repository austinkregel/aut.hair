<?php

namespace Tests\App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;
use Tests\App\CustomNewEvent;
use Tests\App\Listeners\LogAuthenticatedUserListener;

class EventServiceProviderWithCustomEvent extends ServiceProvider
{
    protected $listen = [
        CustomNewEvent::class => [
            LogAuthenticatedUserListener::class
        ],
        SocialiteWasCalled::class => [
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
