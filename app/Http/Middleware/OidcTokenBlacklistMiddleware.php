<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Token\Plain;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejects bearer tokens whose jti has been blacklisted (the
 * `oidc_token_blacklist:{jti}` cache key, written by the revocation/logout
 * controllers and the ChromeOS device blacklist action).
 *
 * Needed because the OIDC access tokens are self-contained JWTs — Passport's
 * `auth:api` guard validates the signature but does not, on its own, reject a
 * token flagged `revoked` in the DB. This middleware closes that gap for routes
 * it is applied to.
 */
class OidcTokenBlacklistMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $jti = $this->jtiFromBearer($request->bearerToken());

        if ($jti !== null && Cache::has('oidc_token_blacklist:'.$jti)) {
            return response()->json(['error' => 'invalid_token'], 401);
        }

        return $next($request);
    }

    private function jtiFromBearer(?string $token): ?string
    {
        if (! $token) {
            return null;
        }

        try {
            $jwt = (new Parser(new JoseEncoder()))->parse($token);
            if (! $jwt instanceof Plain) {
                return null;
            }

            $jti = $jwt->claims()->get('jti');

            return is_string($jti) && $jti !== '' ? $jti : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
