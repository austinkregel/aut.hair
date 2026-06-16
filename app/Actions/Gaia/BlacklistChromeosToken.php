<?php

namespace App\Actions\Gaia;

use App\Models\ChromeosDeviceToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Token;

/**
 * Blacklist (revoke) a token issued to a ChromeOS device, by its jti
 * (== oauth_access_tokens.id).
 *
 * The durable, enforced mechanism is `oauth_access_tokens.revoked = true`, which
 * Passport's `auth:api` guard honors immediately (so the GAIA userinfo endpoint
 * stops accepting the token). We also write the `oidc_token_blacklist:{jti}`
 * cache key (forward-compatible with a cache-layer middleware, currently inert)
 * and flag the local audit row for the admin UI.
 *
 * Mirrors OidcTokenRevocationController::revokeAccessToken.
 */
class BlacklistChromeosToken
{
    public function revoke(string $jti): bool
    {
        $token = Token::find($jti);

        if (! $token) {
            return false;
        }

        $token->revoked = true;
        $token->save();

        DB::table('oauth_refresh_tokens')
            ->where('access_token_id', $jti)
            ->update(['revoked' => true]);

        Cache::put('oidc_token_blacklist:'.$jti, true, now()->addDay());

        ChromeosDeviceToken::where('jti', $jti)->update(['revoked' => true]);

        return true;
    }
}
