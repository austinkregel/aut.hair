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
 * PRIMARY MECHANISM (Credentials Passing API, PR #46 / v1.6.8):
 *
 * Login.vue stashes the typed password in sessionStorage['__gaia_api_pwd__']
 * before the Inertia XHR. When the webview navigates to embedded-setup.blade.php
 * and the host sends `handshake`, the page reads the password and sends:
 *   1. window.postMessage({type:'gaia_saml_api', call:{method:'add',...}})
 *   2. window.postMessage({type:'gaia_saml_api', call:{method:'confirm',...}})
 * These go through saml_injected.js's APICallForwarder (always active, not gated
 * on SAML mode) → saml_handler.js stores lastApiPasswordBytes_ and sets
 * confirmToken_, clearing waitApiPasswordConfirm_ in authenticator.js.
 * A 200 ms setTimeout before the userInfo + closeView signals gives the IPC
 * (saml_injected.js → saml_handler.js) time to complete so maybeCompleteAuth_()
 * sees samlApiUsed = true and password_ = apiPasswordBytes.
 *
 * The `add` is sent from embedded-setup (not from /login) because the channel
 * is GUARANTEED established there — handshake is sent in onContentLoad_ which
 * fires after full page load, by which time channel.connect('injected') has
 * already completed. From /login the IPC may not arrive before the webview
 * navigates away, leaving lastApiPasswordBytes_ = null → empty password → powerwash.
 * Login.vue keeps the window.postMessage as a belt-and-suspenders (no harm if
 * it also arrives; saml_handler.js just overwrites with the same value).
 * Outside the OOBE webview sessionStorage and postMessage are both ignored.
 *
 * SAML MODE (belt-and-suspenders, PasswordInputScraper):
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
        $response = $next($request);

        // Belt-and-suspenders: also emit the header on the login page.
        // Keeps pendingIsSamlPage_ = true throughout the /login navigation so
        // isSamlPage_ remains true at /login's own loadcommit and beyond.
        // The primary SAML header is emitted by EmbeddedSetupController on the
        // bootstrap page that precedes /login.
        if ($request->routeIs('login') && $this->isGaiaFlow($request)) {
            $response->headers->set('google-accounts-saml', 'start');
        }

        return $response;
    }

    private function isGaiaFlow(Request $request): bool
    {
        if (! $request->hasSession()) {
            return false;
        }

        return str_contains((string) $request->session()->get('url.intended', ''), 'embedded/setup/v2/chromeos');
    }
}
