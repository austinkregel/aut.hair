<?php

namespace App\Http\Controllers;

use App\Providers\EventServiceProvider;
use App\Services\Code;
use App\Services\Programming\LaravelProgrammingStyle;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class AvailableLoginProvidersController extends Controller
{
    public function __invoke()
    {
        $allPossibleServices = array_keys(array_filter(
            config('services'),
            fn ($service) => !empty($service['redirect']) && !empty($service['client_id'])
        ));

        $allPossibleServices = array_filter($allPossibleServices, function ($service) {
            try {
                Socialite::driver($service);
                return true;
            } catch (\Throwable $e) {
                return false;
            }
        });

        return array_values(array_map(function ($serviceName) {
            return [
                'name' => Str::title($serviceName),
                'value' => $serviceName,
                'redirect' => Socialite::driver($serviceName)->redirect()->getTargetUrl(),
            ];
        }, $allPossibleServices));
    }
}
