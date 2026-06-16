<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Concerns\ExtractsTokenId;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejects bearer access tokens that have been revoked or blacklisted.
 *
 * Needed because the OIDC access tokens are self-contained JWTs — Passport's
 * `auth:api` guard validates the signature but does not, on its own, reject a
 * token flagged `revoked` in the DB. This middleware closes that gap for the
 * bearer-protected routes it is applied to, enforcing every revocation path:
 *
 *   - the `oidc_token_blacklist:{jti}` cache key (RFC 7009 revoke of an unknown
 *     JWT, OIDC logout id_token, the ChromeOS device blacklist action), and
 *   - `oauth_access_tokens.revoked = true` (the /oauth/revoke endpoint and the
 *     machine-token / ChromeOS revoke actions, which flip the DB flag).
 */
class OidcTokenBlacklistMiddleware
{
    use ExtractsTokenId;

    public function handle(Request $request, Closure $next): Response
    {
        $jti = $this->extractTokenId($request->bearerToken() ?? '');

        if ($jti !== null && $this->isRevoked($jti)) {
            return response()->json(['error' => 'invalid_token'], 401);
        }

        return $next($request);
    }

    private function isRevoked(string $jti): bool
    {
        if (Cache::has('oidc_token_blacklist:'.$jti)) {
            return true;
        }

        return DB::table('oauth_access_tokens')
            ->where('id', $jti)
            ->where('revoked', true)
            ->exists();
    }
}
