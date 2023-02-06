<?php

namespace App\Http\Controllers;

use App\Events\ComposerActionFailed;
use App\Events\ComposerActionFinished;
use App\Events\ComposerActionLoggedToConsole;
use App\Events\SubscribeToJobEvent;
use App\Providers\EventServiceProvider;
use App\Services\Code;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\Factory;
use Composer\IO\BufferIO;
use Composer\Package\Package;
use Composer\Repository\InstalledRepository;
use Illuminate\Bus\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Nette\PhpGenerator\Literal;
use Nette\PhpGenerator\Property;
use SocialiteProviders\Manager\SocialiteWasCalled;
use Symfony\Component\Process\Process;

class EnableProviderController extends Controller
{
    public function __invoke(Filesystem $filesystem)
    {
        request()->validate([
            'name' => 'required|string',
            'client_id' => 'required',
            'client_secret' => 'required',
            'redirect' => 'required'
        ]);
        $name = request()->get('name');

        $jobId = Str::uuid();

        $queuedInstalledProcess = function () use ($jobId, $filesystem, $name) {
            // enabling is based on if the driver is supported, so we need to remove support from the event service provider.
            $providers = json_decode(file_get_contents(storage_path('provider-information.json')), true);
            $driversToEnable = array_values(array_filter($providers['installed'], fn($package) => $package['name'] === $name));

            abort_if(count($driversToEnable) === 0, 404, 'No drivers installed by this composer vendor name');

            broadcast(new ComposerActionLoggedToConsole($jobId, "Attempting to identify the driver needed\r\n"));

            $configuredServices = collect(config('services'))->filter(function ($service, $key) {
                return !empty($service['client_id'])
                    && !empty($service['client_secret'])
                    && !empty($service['redirect']);
            });;

            broadcast(new ComposerActionLoggedToConsole($jobId, "\e[01;32mThere are {$configuredServices->count()} possible oauth/socialite services.\r\n"));

            $errors = collect([]);
            $code = Code::for(EventServiceProvider::class);
            foreach ($driversToEnable as $vendor) {
                $drivers = $vendor['drivers'];

                foreach ($drivers as $class => $driverName) {
                    try {
                        if (!$configuredServices->has($driverName)) {
                            broadcast(new ComposerActionLoggedToConsole($jobId, "\e[01;31mThere are no services configured for driver [" . $driverName . "]. Please update your config/services.php config file.\r\n"));
                            continue;
                        }

                        $code->addValueToProperty('listen', SocialiteWasCalled::class, $class);

                        broadcast(new ComposerActionLoggedToConsole($jobId, "\e[01;32mLooks like we are able to add the event listener automatically.\r\nAttempting to save file...\r\n"));

                        $code->toFile(Code::REPLACE_FILE);
                        broadcast(new ComposerActionLoggedToConsole($jobId, "\e[01;33mFile: app/Providers/EventServiceProvider.php updated\r\n"));
                    } catch (\Throwable $e) {
                        broadcast(new ComposerActionLoggedToConsole($jobId, $e->getMessage()));
                        $errors->push($e->getMessage());
                    } finally {
                        // We don't want to close the modal in the UI, so don't launch an event to say the job completed.
                    }
                }
            }
        };

        broadcast(new SubscribeToJobEvent(request()->user()->id, $jobId));

        dispatch($queuedInstalledProcess)->delay(5);
    }
}
