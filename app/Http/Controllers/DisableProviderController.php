<?php

namespace App\Http\Controllers;

use App\Events\ComposerActionFailed;
use App\Events\ComposerActionFinished;
use App\Events\ComposerActionLoggedToConsole;
use App\Providers\EventServiceProvider;
use App\Services\Code;
use App\Services\Programming\LaravelProgrammingStyle;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\Factory;
use Composer\IO\BufferIO;
use Composer\Package\Package;
use Composer\Repository\InstalledRepository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Nette\PhpGenerator\Literal;
use Nette\PhpGenerator\Property;
use SocialiteProviders\Manager\SocialiteWasCalled;
use Symfony\Component\Process\Process;

class DisableProviderController extends Controller
{
    public function __invoke(Filesystem $filesystem)
    {
        request()->validate([
            'name' => 'required|string',
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
            $code = LaravelProgrammingStyle::for(EventServiceProvider::class);
            foreach ($driversToEnable as $vendor) {
                $drivers = $vendor['drivers'];

                foreach ($drivers as $class => $driverName) {
                    try {
                        if (!$configuredServices->has($driverName)) {
                            broadcast(new ComposerActionLoggedToConsole($jobId, "\e[01;31mThere are no services configured for driver [" . $driverName . "]. Please update your config/services.php config file.\r\n"));
                            continue;
                        }

                        $code->removeListenerFromEvent(SocialiteWasCalled::class, $class);

                        broadcast(new ComposerActionLoggedToConsole($jobId, "\e[01;32mLooks like we are able to disable the event listener automatically.\r\n"
                        ."Attempting to save file...\r\n"));

                        $code->toFile(Code::REPLACE_FILE);
                        broadcast(new ComposerActionLoggedToConsole($jobId, "\e[01;33mFile: app/Providers/EventServiceProvider.php updated successfully.\r\n"));
                    } catch (\Throwable $e) {
                        broadcast(new ComposerActionLoggedToConsole($jobId, $e->getMessage()));
                        $errors->push($e->getMessage());
                    } finally {
                        // We don't want to close the modal in the UI, so don't launch an event to say the job completed.
                    }
                }
            }

            broadcast(new ComposerActionLoggedToConsole($jobId, "\e[01;33mDisabling complete, you can close this window.\r\n"));
        };

        dispatch($queuedInstalledProcess)->delay(5);
    }
}
