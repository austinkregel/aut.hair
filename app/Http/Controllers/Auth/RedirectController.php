<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Laravel\Socialite\Facades\Socialite;

class RedirectController extends Controller
{
    public function __invoke($provider)
    {
        try {
            Socialite::driver($provider);
        } catch (\InvalidArgumentException $e) {
            abort(404);
        }

        return Socialite::driver($provider)->redirect();
    }
}
