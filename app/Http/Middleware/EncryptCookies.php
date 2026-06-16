<?php

namespace App\Http\Middleware;

use Illuminate\Cookie\Middleware\EncryptCookies as Middleware;

class EncryptCookies extends Middleware
{
    /**
     * The names of the cookies that should not be encrypted.
     *
     * @var array<int, string>
     */
    protected $except = [
        // openFyde/ChromeOS reads this cookie raw (it's the GAIA oauth_code the
        // device exchanges at oauth2/v4/token), so it must NOT be Laravel-
        // encrypted. See app/Http/Controllers/Gaia/EmbeddedSetupController.
        'oauth_code',
    ];
}
