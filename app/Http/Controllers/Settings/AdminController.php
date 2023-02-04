<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Providers\EventServiceProvider;
use App\Services\Code;
use App\Services\Programming\LaravelProgrammingStyle;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Laravel\Socialite\Facades\Socialite;
use SocialiteProviders\Google\GoogleExtendSocialite;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AdminController extends Controller
{
    public function __invoke()
    {
        $installedNotInstalled = json_decode(file_get_contents(storage_path('provider-information.json')), true);

        /** @var Collection $serviceWithOauthish */
        $serviceWithOauthish = collect(config('services'));
        $serviceWithOauthish = $serviceWithOauthish->filter(function ($service, $key) {
                return !empty($service['client_id'])
                    && !empty($service['client_secret'])
                    && !empty($service['redirect']);
            })->reduce(function ($result, $config, $service) use ($installedNotInstalled) {

            try {
                $installedServiceThatMatchesInstalledDriver = array_values(array_filter($installedNotInstalled['installed'], fn($value) => in_array($service, $value['drivers'] ?? [])));
                $driver = Arr::first($installedServiceThatMatchesInstalledDriver) ?? [];
                foreach ($driver['drivers'] ?? [] as $eventListener => $driverName) {
                    $foundListener = Code::with(LaravelProgrammingStyle::class)
                        ->for(EventServiceProvider::class)
                        ->propertyContainsValue('listen', $eventListener);

                    if ($foundListener) {
                        return array_merge($result, [
                            'enabled' => array_merge($result['enabled'] ?? [], [
                                $service => $config,
                            ]),
                        ]);
                    }

                    return array_merge($result, [
                        'disabled' => array_merge($result['disabled'] ?? [], [
                            $service => $config,
                        ]),
                    ]);
                }
            } catch (\Throwable $e) {
                return array_merge($result, [
                    'disabled' => array_merge($result['disabled'] ?? [], [
                        $service => $config,
                    ]),
                ]);
            }

            return array_merge($result, [
                'disabled' => array_merge($result['disabled'] ?? [], [
                    $service => $config,
                ]),
            ]);
        }, [
            'enabled' => [],
            'disabled' => [],
        ]);

         return Inertia::render('Admin', array_merge($installedNotInstalled, $serviceWithOauthish));
    }
}
