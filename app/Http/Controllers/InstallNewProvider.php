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

            $process->setPty(true);
            $process->start();

            broadcast(new ComposerActionLoggedToConsole($jobId, 'Attempting to install '.$package.".\r\n"));

            foreach ($process as $data) {
                broadcast(new ComposerActionLoggedToConsole($jobId, $data));
            }
            broadcast(new ComposerActionLoggedToConsole($jobId, "Install complete."));

            if ($process->isSuccessful()) {
                broadcast(new ComposerActionFinished($jobId));
            } else {
                broadcast(new ComposerActionFailed($jobId));
            }
        };

        dispatch($queuedInstalledProcess);
        return [
            'id' => $jobId,
        ];
    }
}
