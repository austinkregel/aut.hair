<?php

namespace App\Http\Controllers;

use App\Events\ComposerActionFailed;
use App\Events\ComposerActionFinished;
use App\Events\ComposerActionLoggedToConsole;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\Factory;
use Composer\IO\BufferIO;
use Composer\Package\Package;
use Composer\Repository\InstalledRepository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class EnableProviderController extends Controller
{
    public function __invoke(Filesystem $filesystem)
    {
        request()->validate([
            'name' => 'required|string',
        ]);

        $jobId = Str::uuid();

        $queuedInstalledProcess = function () use ($jobId, $filesystem) {
            // enabling is based on if the driver is supported, so we need to remove support from the event service provider.
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
        };

        dispatch_sync($queuedInstalledProcess);
        return response()->json([
            'id' => $jobId,
        ]);
    }
}
