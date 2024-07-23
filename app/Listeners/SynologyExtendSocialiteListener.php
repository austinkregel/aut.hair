<?php

namespace App\Listeners;

use App\Services\Auth\SynologySocialiteProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;

class SynologyExtendSocialiteListener
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
     * @param  \App\Events\SocialiteWasCalled  $event
     * @return void
     */
    public function handle(SocialiteWasCalled $socialiteWasCalled): void
    {
        $socialiteWasCalled->extendSocialite('synology', SynologySocialiteProvider::class);
    }
}
