<?php

namespace App\Http\Controllers\Gaia;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Passport\Bridge\User as PassportUser;
use League\OAuth2\Server\AuthorizationServer;
use Nyholm\Psr7\Response as Psr7Response;
use Nyholm\Psr7\ServerRequest;

/**
 * GAIA sign-in webview page (`GET embedded/setup/v2/chromeos`).
 *
 * Served to the ChromeOS OOBE <webview>. This route is behind web/auth, so an
 * unauthenticated device hits aut.hair's normal login (credentials + 2FA) first
 * and is redirected back here once authenticated — reusing aut.hair's real auth.
 *
 * Completion contract (see the fyde-fork repo's docs/gaia-shim-spike.md):
 * authenticator.js (the webview host) completes sign-in when it sees, on the
 * response, the `google-accounts-signin` header (email + obfuscatedid/gaia id +
 * sessionindex) AND a `userInfo`+`closeView` postMessage pair, and when the
 * browser then reads the `oauth_code` cookie. The header parser lowercases the
 * whole value, so we emit a lowercased email; the cookie is the authorization
 * code the browser exchanges at oauth2/v4/token. The cookie is exempt from
 * Laravel cookie encryption (EncryptCookies::$except) so the device reads it raw.
 */
class EmbeddedSetupController extends Controller
{
    public function __construct(private AuthorizationServer $server) {}

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
     * Mint a real Passport authorization code for the confidential ChromeOS
     * client (config gaia.client_id, provisioned by `php artisan cros:setup`),
     * approved on behalf of the signed-in user. Chromium exchanges this code at
     * oauth2/v4/token (which reuses AccessTokenController@issueToken; that pulls
     * the team from the client). Team-scoping therefore comes from the client's
     * team_id — no per-code stamping needed here.
     *
     * If the client isn't provisioned yet (no gaia.client_id), fall back to an
     * opaque placeholder + a warning, so the page/contract still works pre-setup.
     */
    private function mintAuthCode($user): string
    {
        $clientId = config('gaia.client_id');

        if (empty($clientId)) {
            Log::warning('gaia: GAIA_CHROMEOS_CLIENT_ID is not set; emitting a placeholder oauth_code. Run `php artisan cros:setup`.');

            return Str::random(64);
        }

        // Drive Passport's own authorize flow server-side: validate the request
        // for the ChromeOS client, approve it for this user, and capture the
        // issued code from the redirect — a genuine, redeemable Passport code.
        $authorizeRequest = (new ServerRequest('GET', rtrim((string) config('app.url'), '/').'/oauth/authorize'))
            ->withQueryParams([
                'response_type' => 'code',
                'client_id' => $clientId,
                'redirect_uri' => config('gaia.redirect_uri'),
                'scope' => implode(' ', (array) config('gaia.scopes', [])),
                'state' => Str::random(40),
                'nonce' => Str::random(40), // harmless if unused; satisfies OIDC when openid is requested
            ]);

        $authRequest = $this->server->validateAuthorizationRequest($authorizeRequest);
        $authRequest->setUser(new PassportUser($user->getAuthIdentifier()));
        $authRequest->setAuthorizationApproved(true);

        $response = $this->server->completeAuthorizationRequest($authRequest, new Psr7Response);

        parse_str((string) parse_url($response->getHeaderLine('Location'), PHP_URL_QUERY), $params);

        return (string) ($params['code'] ?? '');
    }
}
