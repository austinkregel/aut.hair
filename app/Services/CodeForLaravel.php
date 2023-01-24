<?php

namespace App\Services;

use App\Providers\EventServiceProvider;
use Composer\Autoload\ClassLoader;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Nette\PhpGenerator\Dumper;
use Nette\PhpGenerator\Literal;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PhpNamespace;
use Nette\PhpGenerator\Printer;
use Nette\PhpGenerator\Property;
use Symfony\Component\CssSelector\Exception\SyntaxErrorException;
use Symfony\Component\ErrorHandler\Error\FatalError;
use Whoops\Exception\ErrorException;
use function Symfony\Component\Translation\t;

class CodeForLaravel extends Code
{
}
