<?php

namespace App\Http\Controllers\Gaia;

use App\Gaia\GaiaIdentity;
use App\Http\Controllers\Controller;
use App\Models\ChromeosDevice;
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
        // Unauthenticated OOBE webview: return the SAML bootstrap page.
        //
        // ChromeOS's PasswordInputScraper is gated by getSAMLFlag, which queries
        // isSamlPage_ at document_start. Content scripts run BEFORE loadcommit
        // fires for the same page, so isSamlPage_ always reflects the PREVIOUS
        // page's loadcommit. With a server-side 302 (/embedded/setup → /login)
        // there is no loadcommit for /embedded/setup, meaning isSamlPage_ is
        // false when /login's document_start fires and the scraper never inits.
        //
        // Fix: serve a real HTML page here (not a redirect) with
        // google-accounts-saml: start. This gives ChromeOS a loadcommit for
        // /embedded/setup (isSamlPage_ = true). When the JS redirect to /login
        // fires, /login's document_start sees isSamlPage_ = true → scraper inits →
        // password captured → no powerwash. This mirrors real enterprise SAML:
        // GAIA emits the header on its own HTML page, gets its own loadcommit,
        // then the IdP's document_start inherits isSamlPage_ = true.
        if (! $request->user()) {
            if ($request->hasSession()) {
                $request->session()->put('url.intended', $request->fullUrl());
            }

            $loginUrl = json_encode(route('login'), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);

            return response(
                '<!doctype html><html><head><meta charset="utf-8"></head><body>'
                . "<script>window.location.replace($loginUrl);</script>"
                . '</body></html>',
                200
            )->header('Content-Type', 'text/html; charset=utf-8')
             ->header('google-accounts-saml', 'start');
        }

        $user = $request->user();

        // L1: fail closed if the device is built for a DIFFERENT OAuth client than
        // the one we provisioned. Validate only when the param is present (the
        // OOBE page GET sends client_id=<n>); a mismatch is always rejected.
        $expectedClientId = (string) config('gaia.client_id', '');
        $presentedClientId = (string) $request->query('client_id', '');
        abort_if(
            $expectedClientId !== '' && $presentedClientId !== '' && ! hash_equals($expectedClientId, $presentedClientId),
            403,
            'This device is configured for a different sign-in client.'
        );

        $email = strtolower((string) $user->email);
        $gaiaId = GaiaIdentity::for($user); // opaque + stable; coupled with userinfo `id`

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

        // L2 + audit: record the device and log the descriptor. Best-effort —
        // never block sign-in if this fails.
        $deviceId = $request->cookie('device_id') ?: (string) Str::uuid();
        $this->recordDevice($request, $user, $deviceId, $authCode);

        // device_id cookie: server-side soft identity (resets on powerwash). 1yr,
        // HttpOnly/Secure/SameSite=Lax, and kept ENCRYPTED (NOT in
        // EncryptCookies::$except) — unlike oauth_code, the device never reads it.
        Cookie::queue(cookie()->make('device_id', $deviceId, 60 * 24 * 365, '/', null, true, true, false, 'lax'));

        // oauth_code cookie: Path=/, HttpOnly, Secure, SameSite=None — required
        // for the cross-site Set-Cookie the webview's signin partition accepts.
        // Raw (exempt from cookie encryption) so the device reads the code verbatim.
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
     * Record/refresh the device row (keyed by the device_id cookie) and audit the
     * sign-in. `last_code_hash` is the correlation key the token endpoint uses to
     * tie an issued token back to this device. `approved` is only set on first
     * sight so an admin's later decision survives re-sign-ins.
     */
    private function recordDevice(Request $request, $user, string $deviceId, string $code): void
    {
        try {
            $device = ChromeosDevice::updateOrCreate(
                ['device_id' => $deviceId],
                [
                    'team_id' => $user->currentTeam?->id,
                    'user_id' => $user->getKey(),
                    'last_code_hash' => hash('sha256', $code),
                    'last_seen_ip' => $request->ip(),
                    'last_user_agent' => $request->userAgent(),
                    'last_seen_at' => now(),
                ]
            );

            // `approved` is set only on first creation so an admin's later
            // decision survives re-sign-ins.
            if ($device->wasRecentlyCreated) {
                $device->approved = (bool) config('gaia.auto_approve_devices', true);
                $device->save();
            }

            activity()
                ->causedBy($user)
                ->withProperty('endpoint', $request->path())
                ->withProperty('query', $request->query()) // all params incl. unknown (mi, etc.)
                ->withProperty('ip', $request->ip())
                ->withProperty('user_agent', $request->userAgent())
                ->withProperty('device_id', $deviceId)
                ->log('gaia device sign-in');

            Log::channel('gaia')->debug('embedded-setup', [
                'endpoint' => $request->path(),
                'query' => $request->query(),
                'device_id' => $deviceId,
                'user_id' => $user->getKey(),
            ]);
        } catch (\Throwable $e) {
            Log::channel('gaia')->error('embedded-setup device record failed', ['error' => $e->getMessage()]);
        }
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
