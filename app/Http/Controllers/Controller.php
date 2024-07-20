<?php

namespace App\Http\Controllers;

use App\Events\ComposerActionFailed;
use App\Events\ComposerActionFinished;
use App\Events\ComposerActionLoggedToConsole;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Symfony\Component\Process\Process;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    protected function runProcess(string $jobId, Process $process)
    {
        $process->setPty(true);
        $process->start();

        broadcast(new ComposerActionLoggedToConsole($jobId, 'Process has started to execute '.$jobId.".\r\n"));

        foreach ($process as $data) {
            broadcast(new ComposerActionLoggedToConsole($jobId, $data));
        }
        broadcast(new ComposerActionLoggedToConsole($jobId, 'Install complete.'));

        if ($process->isSuccessful()) {
            broadcast(new ComposerActionFinished($jobId));
        } else {
            broadcast(new ComposerActionFailed($jobId));
        }
    }
}
