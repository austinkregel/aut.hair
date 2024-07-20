<?php

namespace App\Http\Controllers;

use App\Events\ComposerActionFailed;
use App\Events\ComposerActionFinished;
use App\Events\ComposerActionLoggedToConsole;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class InstallNewProvider extends Controller
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
            $process = new Process(['composer', 'require', $package], base_path(), ["COMPOSER_HOME" => "~/.composer"]);

            $this->runProcess($jobId, $process);
        };

        $headerLogs = iterator_to_array(request()->headers->getIterator());

        unset($headerLogs['cookie']);
        unset($headerLogs['authorization']);
        unset($headerLogs['x-csrf-token']);
        unset($headerLogs['x-xsrf-token']);

        activity()
            ->causedBy(auth()->user())
            ->withProperty('ip', request()->ip())
            ->withProperty('headers', $headerLogs)
            ->log('installed '.$package);
        dispatch($queuedInstalledProcess)->delay(5);
    }
}
