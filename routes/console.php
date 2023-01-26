<?php

use App\Listeners\SynologyExtendSocialiteListener;
use App\Services\Code;
use Illuminate\Support\Facades\Artisan;
use SocialiteProviders\Manager\SocialiteWasCalled;

if (! function_exists('class_implements_recursive')) {
    function class_implements_recursive($class)
    {
        if (is_object($class)) {
            $class = get_class($class);
        }

        $results = [];

        try {
//        !empty(class_implements($class)) ? dd(class_implements($class)) : [];
            $implementations = [];
            try {
                $implementations = class_implements($class);
            } catch (\Throwable $e) {
            }

            foreach (array_reverse($implementations) as $class) {
                $results += class_implements_recursive($class);
            }
        } catch (\Throwable $e) {
        } finally {
//        $results += [$class => $class];
            return array_unique($results);
        }
    }
}
Artisan::command('test2', function () {
//    Code::with('laravel')
//        ->for(App\Providers\EventServiceProvider::class)
//        ->addListenerToEvent(SocialiteWasCalled::class, SynologyExtendSocialiteListener::class)
//        ->toFile();

    // vendor/composer/composer/src/Composer/Command/RequireCommand.php
//    View the above command

//    dd(Code::with('laravel')
//        ->for(App\Providers\AuthServiceProvider::class)
//        ->addValueToProperty('policies', App\Models\Team::class, App\Policies\TeamPolicy::class)
//        ->toFile());
});
Artisan::command('socialite:discover', function () {
    // discover installed socialite providers.
//    $autoload = require './vendor/autoload.php';
//
//    $providers = [];
//
//    foreach ($autoload->getClassMap() as $class => $location) {
//        if (str_contains($location, 'symfony')) {
//            continue;
//        }
//
//        if (str_contains($class, 'Provider')) {
//            $implementations = class_implements_recursive($class);
//            if (in_array(\Laravel\Socialite\Two\ProviderInterface::class, $implementations)) {
//                $providers = array_merge($providers, $implementations);
//            }
//        }
//    }
    $files = collect(json_decode(file_get_contents(base_path('composer.lock')))->packages)
    ->filter(function ($jsonFile) {
        if (isset($jsonFile->name)) {
            if ($jsonFile->name === 'socialiteproviders/manager') {
                return true;
            }
        }

        if (isset($jsonFile->require)) {
            return isset($jsonFile->require->{'socialiteproviders/manager'});
        }
        if (isset($jsonFile->{'require-dev'})) {
            return isset($jsonFile->{'require-dev'}->{'socialiteproviders/manager'});
        }

        return false;
    });

    $installed = $files->map(function ($contents) {
        return [
            'name' => $contents->name,
            'description' => $contents->description,
            'version' => $contents->version,
            'time' => \Carbon\Carbon::parse($contents->time)->format('F j, Y H:i:s'),
            'installed' => true,
        ];
    })->values();
    $page = 1;
    $installedNames = $installed->map->name;

    $uninstalled = [];
    do {
        $response = \Illuminate\Support\Facades\Http::get('https://packagist.org/search.json?q=socialiteproviders&per_page=100&page='.$page++)->json();
        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $response['results'],
            $response['total'],
            100,
            $page - 1,
        );

        foreach ($paginator->items() as $provider) {
            if ($installedNames->contains($provider['name'])) {
                continue;
            }

            $uninstalled[] = [
                'name' => $provider['name'],
                'description' => $provider['description'],
                'downloads' => $provider['downloads'],
                'installed' => false,
            ];
        }
    } while ($paginator->hasMorePages());

    $enabled = collect(config('services'))
        ->filter(function ($service, $key) {
            return \Illuminate\Support\Arr::has($service, ['client_id', 'client_secret', 'redirect']);
        });

    // We need a way to add the handle method to the event service provider.
    // Also, we might want to change how we identify enabled/disabled values.
    file_put_contents(storage_path('provider-information.json'),  json_encode([
        'enabled' => $enabled->filter(function ($config, $service) {
            try {
                \Laravel\Socialite\Facades\Socialite::driver($service);
                return true;
            } catch (\Throwable $exception) {
                return false;
            }
        }),
        'disabled' => $enabled->filter(function ($service) {
            try {
                \Laravel\Socialite\Facades\Socialite::driver($service);
                return false;
            } catch (\Throwable $exception) {
                return true;
            }
        }),
        'installed' => $installed,
        'notInstalled' => collect($uninstalled)->sortByDesc('downloads')->values(),
    ]));
});
