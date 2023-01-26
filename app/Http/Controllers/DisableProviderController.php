<?php

namespace App\Http\Controllers;

use App\Events\ComposerActionLoggedToConsole;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\Factory;
use Composer\IO\BufferIO;
use Composer\Package\Package;
use Composer\Repository\InstalledRepository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class DisableProviderController extends Controller
{
    public function __invoke(Filesystem $filesystem)
    {
        request()->validate([
            'name' => 'required|string',
        ]);

        // We need to use the "disable provider controller"
        // to remove the 'handle' listener call in the event service provider.
    }
}
