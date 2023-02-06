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

class UninstallNewProvider extends Controller
{
    public function __invoke(Filesystem $filesystem)
    {
        request()->validate([
            'name' => 'required|string',
        ]);

        $jobId = Str::uuid();
        $package = request()->get('name');
        abort_if(empty($package), 404);

        $queuedInstalledProcess = function () use ($jobId, $filesystem, $package) {
            $process = new Process(['composer', 'remove', $package], base_path(), ["COMPOSER_HOME" => "~/.composer"]);

            $this->runProcess($jobId, $process);
        };

        dispatch($queuedInstalledProcess)->delay(5);
    }
}
