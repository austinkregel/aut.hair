<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Puts ChromeOS into SAML mode during the openFyde GAIA OOBE flow so it captures
 * the password the user types as the cryptohome "gaia" knowledge factor.
 *
 * Why this exists: ChromeOS derives the encrypted-home key from the typed
 * password, never from the OAuth token. If sign-in completes with no password
 * captured (a "passwordless owner"), ChromeOS arms a silent powerwash and wipes
 * the device on the next boot. SAML mode is gated entirely by one response
 * header: `google-accounts-saml: start` turns it on, `: end` turns it off (the
 * value is matched, not just presence; saml_handler.js:823-830 in the openFyde
 * r132 tree).
 *
 * Once in SAML mode ChromeOS's injected PasswordInputScraper (saml_injected.js)
 * auto-scrapes any input[type=password] on the login page and stores the value in
 * passwordStore_ on the handler side — this persists across the /login →
 * /embedded/setup navigation because samlHandler_.reset() is never called between
 * those two pages. When maybeCompleteAuth_ runs after the token exchange it finds
 * scrapedPasswordCount === 1 and sets password_ = firstScrapedPassword, which
 * becomes the cryptohome factor.
 *
 * We do NOT inject the Chrome Credentials Passing API (gaia_saml_api) handshake.
 * The API's `add` call sets samlApiUsed = true (overriding scraping) but `confirm`
 * is sent on form submit — it is lost when the page navigates before the extension
 * channel round-trip completes. With samlApiUsed true and confirmToken_ null,
 * apiPasswordBytes returns null and password_ ends up null → powerwash. The DOM
 * scraper avoids this race entirely because it fires on keystroke, not on submit.
 *
 * THE TIMING PROBLEM (why /embedded/setup must return HTML, not a 302):
 *
 * ChromeOS injects saml_injected.js at `document_start` for every page in the
 * webview (saml_handler.js:382-390). At document_start, it immediately sends a
 * `getSAMLFlag` RPC (saml_injected.js:214). The handler responds with
 * `this.isSamlPage_` (saml_handler.js:1026). `isSamlPage_` is only updated at
 * `loadcommit` (saml_handler.js:572). In Chromium's architecture, document_start
 * content scripts run in the renderer during document creation, and `loadcommit`
 * fires only after the browser process receives DidCommitNavigation from the
 * renderer — so content scripts always run BEFORE loadcommit for the same page.
 *
 * Consequence: when /login's content scripts query getSAMLFlag, isSamlPage_
 * reflects the PREVIOUS page's loadcommit (initial state = false), not /login's
 * own pendingIsSamlPage_. The PasswordInputScraper is never initialized and the
 * device powerwashes.
 *
 * Fix: serve a real HTML page at /embedded/setup (not a 302) that carries
 * google-accounts-saml: start and JS-redirects to /login. This gives ChromeOS a
 * loadcommit for /embedded/setup (isSamlPage_ = true). When the browser then
 * follows the JS redirect to /login, document_start fires before /login's
 * loadcommit — but now isSamlPage_ is true (from /embedded/setup's loadcommit),
 * so the scraper initializes, captures the typed password, and stops the powerwash.
 *
 * This mirrors how real enterprise SAML works: GAIA (accounts.google.com) emits
 * the header and gets its own loadcommit; the actual IdP page's document_start
 * then sees isSamlPage_ = true from GAIA's prior loadcommit.
 */
class GaiaSamlMode
{
    public function handle(Request $request, Closure $next): Response
    {
        // Laravel's Authenticate middleware throws AuthenticationException rather
        // than returning a RedirectResponse. For the /embedded/setup route we need
        // to intercept that throw so we can return our SAML-initialising HTML page
        // instead of letting the exception handler produce a plain 302.
        try {
            $response = $next($request);
        } catch (\Illuminate\Auth\AuthenticationException $e) {
            if (! $request->routeIs('gaia.embedded-setup')) {
                throw $e;
            }

            return $this->samlRedirectPage($request);
        }

        // Fallback for any non-throw redirect (guard implementations that return
        // a RedirectResponse rather than throwing).
        if ($request->routeIs('gaia.embedded-setup') && $response->isRedirect()) {
            return $this->samlRedirectPage($request);
        }

        // Belt-and-suspenders: also emit the header on the login page itself.
        // Keeps pendingIsSamlPage_ = true throughout the /login navigation so
        // isSamlPage_ remains true at /login's loadcommit and beyond.
        if ($request->routeIs('login') && $this->isGaiaFlow($request)) {
            $response->headers->set('google-accounts-saml', 'start');
        }

        return $response;
    }

    /**
     * Return the HTML intermediate page that arms SAML mode on ChromeOS.
     *
     * See the class docblock for the full explanation. Short version: a JS
     * redirect (not a 302) is required because only a real HTML response gets its
     * own loadcommit, which is what sets isSamlPage_ = true before /login's
     * document_start fires and queries getSAMLFlag.
     */
    private function samlRedirectPage(Request $request): Response
    {
        // Mirror redirect()->guest(): store the intended URL so isGaiaFlow()
        // returns true when the browser subsequently loads /login.
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

    private function isGaiaFlow(Request $request): bool
    {
        if (! $request->hasSession()) {
            return false;
        }

        return str_contains((string) $request->session()->get('url.intended', ''), 'embedded/setup/v2/chromeos');
    }
}
