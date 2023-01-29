<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Inertia\Inertia;

class AdminController extends Controller
{
    public function __invoke()
    {
        $installedNotInstalled = json_decode(file_get_contents(storage_path('provider-information.json')), true);

        $serviceWithOauthish = collect(config('services'));
        $serviceWithOauthish = $serviceWithOauthish->filter(function ($service, $key) {
                return !empty($service['client_id'])
                    && !empty($service['client_secret'])
                    && !empty($service['redirect']);
            });

        return Inertia::render('Admin', array_merge($installedNotInstalled, [
            'enabled' => $serviceWithOauthish->filter(function ($config, $service) {
                try {
                    \Laravel\Socialite\Facades\Socialite::driver($service)->redirect();
                    return true;
                } catch (\Throwable $e) {
                    return false;
                }
            }),
            'disabled' => $serviceWithOauthish->filter(function ($config, $service) {
                try {
                    \Laravel\Socialite\Facades\Socialite::driver($service)->redirect();
                    return false;
                } catch (\Throwable $e) {
                    return true;
                }
            }),
        ]));
    }
}
