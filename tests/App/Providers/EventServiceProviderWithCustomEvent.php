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
            LogAuthenticatedUserListener::class,
        ],
        SocialiteWasCalled::class => [
            //
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
