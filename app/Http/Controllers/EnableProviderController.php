<?php

namespace App\Http\Controllers;

use App\Events\ComposerActionFailed;
use App\Events\ComposerActionFinished;
use App\Events\ComposerActionLoggedToConsole;
use App\Providers\EventServiceProvider;
use App\Services\Code;
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

class EnableProviderController extends Controller
{
    public function __invoke(Filesystem $filesystem)
    {
        request()->validate([
            'name' => 'required|string',
        ]);

        $jobId = Str::uuid();

        $name = request()->get('name');

//        $queuedInstalledProcess = function () use ($jobId, $filesystem, $name) {
            // enabling is based on if the driver is supported, so we need to remove support from the event service provider.

//            try {
                $psr4 = json_decode(file_get_contents(base_path("vendor/$name/composer.json")))->autoload->{'psr-4'};
                $desiredNamespaces = array_keys(get_object_vars($psr4));
                ;
                $classesFromPackage = array_filter(Code::composerMappedClasses(), function ($value) use ($desiredNamespaces) {
                    foreach ($desiredNamespaces as $namespace) {
                        if (str_starts_with($value, $namespace)) {
                            return true;
                        }
                    }
                    return false;
                }, ARRAY_FILTER_USE_KEY);

                $possibleEvents = [];
                foreach ($classesFromPackage as $class => $filePath) {
                    $contents = file_get_contents($filePath);

                    if (str_contains($contents, 'public function handle(SocialiteWasCalled')) {
                        $possibleEvents[] = $class;
                    }
                }

                $code = Code::for(EventServiceProvider::class);
                foreach ($possibleEvents as $value) {
                    $code->addValueToProperty('listen', SocialiteWasCalled::class, $value);
                }

                $code->toFile(Code::REPLACE_FILE);
//            } catch (\Throwable $e) {
//
//            }

//            $process = new Process(['composer', 'remove', request()->get('name')], base_path(), ["COMPOSER_HOME" => "~/.composer"]);
//
//            $process->setPty(true);
//            $process->start();
//
//            broadcast(new ComposerInstallUpdateLogEvent($jobId, 'Attempting to enable '.request()->get('name').".\n"));
//
//            foreach ($process as $data) {
//                broadcast(new ComposerInstallUpdateLogEvent($jobId, $data));
//            }

//            if ($process->isSuccessful()) {
//                broadcast(new ComposerActionFinished($jobId));
//            } else {
//                broadcast(new ComposerActionFailed($jobId));
//            }
//        };

//        dispatch_sync($queuedInstalledProcess);
        return response()->json([
            'id' => $jobId,
        ]);
    }
}
