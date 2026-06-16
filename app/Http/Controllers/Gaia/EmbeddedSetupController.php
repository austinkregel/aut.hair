<?php

namespace App\Http\Controllers\Gaia;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Passport\Bridge\User as PassportUser;
use Laravel\Passport\Client;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use Nyholm\Psr7\Response as Psr7Response;
use Nyholm\Psr7\ServerRequest;

/**
 * GAIA sign-in webview page (`GET embedded/setup/v2/chromeos`).
 *
 * Served to the ChromeOS OOBE <webview>. Behind web/auth, so an unauthenticated
 * device hits aut.hair's normal login (credentials + 2FA) first and is
 * redirected back once authenticated — reusing aut.hair's real auth.
 *
 * Completion contract (see the fyde-fork repo's docs/gaia-shim-spike.md):
 * authenticator.js completes sign-in when it sees the `google-accounts-signin`
 * header + a `userInfo`+`closeView` postMessage pair, and when the browser then
 * reads the `oauth_code` cookie. The header parser lowercases the whole value,
 * so we emit a lowercased email; the cookie is the authorization code the
 * browser exchanges at oauth2/v4/token, and is exempt from cookie encryption
 * (EncryptCookies::$except) so the device reads it raw.
 */
class EmbeddedSetupController extends Controller
{
    public function __construct(private AuthorizationServer $server) {}

    public function __invoke(Request $request)
    {
        $user = $request->user(); // guaranteed by the auth middleware

        $email = strtolower((string) $user->email);
        $gaiaId = (string) $user->getKey();

        try {
            $authCode = $this->mintAuthCode($user);
        } catch (OAuthServerException $e) {
            // A provisioning mistake (client revoked/missing, redirect_uri
            // mismatch, rejected scope) must NOT 500 the device mid-OOBE. Log it
            // for the admin and show a clean error page that emits no completion
            // signals, so the webview surfaces the failure instead of hanging or
            // crashing.
            Log::error('gaia: could not mint sign-in code — check `php artisan cros:setup` provisioning (client, redirect_uri, scopes). '.$e->getMessage(), [
                'client_id' => config('gaia.client_id'),
                'hint' => $e->getHint(),
            ]);

            return response()->view('gaia.error', [], 200);
        }

        // oauth_code cookie: Path=/, HttpOnly, Secure, SameSite=None.
        Cookie::queue(
            cookie()->make('oauth_code', $authCode, 0, '/', null, true, true, false, 'none')
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
     * oauth2/v4/token; team-scoping comes from the client's team_id.
     *
     * Throws OAuthServerException when the client is misconfigured — handled in
     * __invoke() so it never reaches the device as a 500.
     *
     * If the client isn't provisioned yet (no gaia.client_id), fall back to an
     * opaque placeholder + a warning so the page/contract still works pre-setup.
     */
    private function mintAuthCode($user): string
    {
        $clientId = config('gaia.client_id');

        if (empty($clientId)) {
            Log::warning('gaia: GAIA_CHROMEOS_CLIENT_ID is not set; emitting a placeholder oauth_code. Run `php artisan cros:setup`.');

            return Str::random(64);
        }

        // Source redirect_uri from the client record itself so it can never
        // desync from whatever `cros:setup --redirect` registered (config
        // gaia.redirect_uri is only the default cros:setup uses). A missing
        // client falls through to validateAuthorizationRequest(), which throws.
        $redirectUri = optional(Client::find($clientId))->redirect ?: config('gaia.redirect_uri');

        $authorizeRequest = (new ServerRequest('GET', rtrim((string) config('app.url'), '/').'/oauth/authorize'))
            ->withQueryParams([
                'response_type' => 'code',
                'client_id' => $clientId,
                'redirect_uri' => $redirectUri,
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
