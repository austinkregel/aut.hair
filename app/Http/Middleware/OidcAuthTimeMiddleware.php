<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class OidcAuthTimeMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {
            $authTime = Session::get('oidc_auth_time');

            if (! $authTime) {
                $authTime = now()->timestamp;
                Session::put('oidc_auth_time', $authTime);
            }

            $maxAge = $request->get('max_age');
            if (is_numeric($maxAge)) {
                if ((now()->timestamp - $authTime) > (int) $maxAge) {
                    Auth::logout();
                    Session::invalidate();

                    return redirect()->guest(route('login'));
                }
            }

            $request->attributes->set('auth_time', $authTime);

            // Keep nonce available for token exchange; stored only when provided.
            if ($request->has('nonce')) {
                Session::put('oidc_nonce', $request->get('nonce'));
            }
        }

        return $next($request);
    }
}


