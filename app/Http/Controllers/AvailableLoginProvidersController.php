<?php

namespace App\Http\Controllers;

use App\Providers\EventServiceProvider;
use App\Services\Code;
use App\Services\Programming\LaravelProgrammingStyle;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class AvailableLoginProvidersController extends Controller
{
    public function __invoke()
    {
        $installedNotInstalled = json_decode(file_get_contents(storage_path('provider-information.json')), true);

        /** @var Collection $serviceWithOauthish */
        $serviceWithOauthish = collect(config('services'));

        // From Socialite
        $driversToTry = collect([
            'facebook',
            'twitter',
            'linkedin',
            'google',
            'github',
            'gitlab',
            'bitbucket',
        ])->filter(function ($service) {
            try {
                return collect(config('services.'.$service))->filter()->isNotEmpty();
            } catch (\Throwable $exception) {
                return false;
            }
        })->reduce(fn ($services, $service) => array_merge($services, [
            $service => array_merge(($services[$service] ?? []), [
                'name' => Str::title($service),
                'value' => $service,
                'redirect' => Socialite::driver($service)->redirect()->getTargetUrl(),
            ]),
        ]), []);
        $serviceWithOauthish = $serviceWithOauthish->filter(function ($service, $key) {
            return ! empty($service['client_id'])
                && ! empty($service['client_secret'])
                && ! empty($service['redirect']);
        })
            ->reduce(function ($result, $config, $service) use ($installedNotInstalled) {
                try {
                    $installedServiceThatMatchesInstalledDriver = array_values(array_filter($installedNotInstalled['installed'], fn ($value) => in_array($service, $value['drivers'] ?? [])));
                    $driver = Arr::first($installedServiceThatMatchesInstalledDriver) ?? [];
                    foreach ($driver['drivers'] ?? [] as $eventListener => $driverName) {
                        $foundListener = Code::with(LaravelProgrammingStyle::class)
                            ->for(EventServiceProvider::class)
                            ->propertyContainsValue('listen', $eventListener);

                        if ($foundListener) {
                            $result = array_merge($result, [
                                'enabled' => array_merge($result['enabled'] ?? [], [
                                    $service => [
                                        'name' => Str::title($service),
                                        'value' => $service,
                                        'redirect' => Socialite::driver($service)->redirect()->getTargetUrl().'&intended='.urlencode('/user/oauth'),
                                    ],
                                ]),
                            ]);
                        }

                        $result = array_merge($result, [
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
                'enabled' => $driversToTry,
                'disabled' => [],
            ])['enabled'];

        return array_values($serviceWithOauthish);
    }
}
