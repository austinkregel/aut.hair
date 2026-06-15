<?php

namespace App\Http\Controllers\Gaia;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * GAIA sign-in webview page (`GET embedded/setup/v2/chromeos`).
 *
 * Served to the ChromeOS OOBE <webview>. This route is behind web/auth, so an
 * unauthenticated device hits aut.hair's normal login (credentials + 2FA) first
 * and is redirected back here once authenticated — reusing aut.hair's real auth
 * rather than reimplementing it.
 *
 * Completion contract (see the fyde-fork repo's docs/gaia-shim-spike.md):
 * authenticator.js (the webview host) completes sign-in only when it sees, on
 * the response, the `google-accounts-signin` header (email + obfuscatedid/gaia
 * id + sessionindex) AND a `userInfo`+`closeView` postMessage pair, and when the
 * browser later reads the `oauth_code` cookie. The header parser lowercases the
 * whole value, so we emit a lowercased email; the cookie is the auth code the
 * browser exchanges at oauth2/v4/token.
 *
 * SLICE 2a: this lands the page + the byte-exact header/cookie/postMessage
 * contract (CI-testable). SLICE 2b replaces mintAuthCode() with a real Passport
 * authorization code bound to the confidential "ChromeOS" client (+ team), and
 * validates token redemption + the live webview handshake in a cros vm.
 */
class EmbeddedSetupController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user(); // guaranteed by the auth middleware

        $email = strtolower((string) $user->email);
        $gaiaId = (string) $user->getKey();

        // oauth_code cookie: Path=/, HttpOnly, Secure, SameSite=None — required
        // for the cross-site Set-Cookie the webview's signin partition accepts.
        Cookie::queue(
            cookie()->make('oauth_code', $this->mintAuthCode($user), 0, '/', null, true, true, false, 'none')
        );

        return response()
            ->view('gaia.embedded-setup', ['services' => []])
            ->header(
                'google-accounts-signin',
                sprintf('email="%s", obfuscatedid="%s", sessionindex=0', $email, $gaiaId)
            );
    }

    /**
     * TODO(slice 2b): mint a REAL Passport authorization code bound to the
     * confidential "ChromeOS" client (and its team, per the #37 client-team
     * binding), redeemable at oauth2/v4/token. That depends on the confidential
     * client existing (separate auth fix) and is validated on-device.
     *
     * For now this is an opaque placeholder so the cookie/header/postMessage
     * contract is exercised and CI-tested end to end on the page side; only the
     * token-exchange redemption is deferred.
     */
    private function mintAuthCode($user): string
    {
        return Str::random(64);
    }
}
