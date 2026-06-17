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
 * the device on the next boot. SAML mode — and therefore password capture — is
 * gated entirely by one response header: `google-accounts-saml: start` turns it
 * on, `: end` turns it off (the value is matched, not just the header's presence;
 * saml_handler.js:823-830 in the openFyde r132 tree). We emit `start` on the
 * login page and run the Chrome Credentials Passing API (`gaia_saml_api`)
 * handshake to hand ChromeOS the password explicitly.
 *
 * Scoped to the GAIA flow via a session flag set when /embedded/setup is hit
 * (even when the unauthenticated device is bounced to /login), so normal web
 * logins are unaffected. No-op outside the flow and in any non-ChromeOS browser
 * (nothing listens for the gaia_saml_api messages there).
 */
class GaiaSamlMode
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // SAML mode + password capture belong on the login page (where the
        // password field lives), and only inside the GAIA OOBE flow. We detect
        // the flow via Laravel's intended-URL: when an unauthenticated device
        // hits /embedded/setup, `auth` stores that URL as `url.intended` for the
        // post-login redirect — a signal guaranteed to survive the bounce. The
        // completion page (/embedded/setup) stays a plain GAIA response so the
        // existing google-accounts-signin + oauth_code path finishes sign-in.
        if ($request->routeIs('login') && $this->isGaiaFlow($request)) {
            // Must be exactly "start" — ChromeOS lowercases and matches the value
            // against start/end; anything else is ignored and SAML mode never arms.
            $response->headers->set('google-accounts-saml', 'start');
            $this->injectHandshake($response);
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

    private function injectHandshake(Response $response): void
    {
        $content = $response->getContent();

        if (! is_string($content)
            || ! str_contains((string) $response->headers->get('Content-Type'), 'text/html')
            || ! str_contains($content, '</body>')) {
            return;
        }

        $response->setContent(str_replace('</body>', $this->script().'</body>', $content));
    }

    /**
     * The Chrome Credentials Passing API handshake (gaia_saml_api): initialize
     * the API, then on form submit hand the typed password to ChromeOS as a
     * KEY_TYPE_PASSWORD_PLAIN credential (add + confirm). authenticator.js reads
     * this at maybeCompleteAuth_ and sets it as the cryptohome factor — taking
     * priority over DOM scraping. A no-op in any browser that isn't ChromeOS in
     * SAML mode (nothing forwards the messages).
     */
    private function script(): string
    {
        return <<<'HTML'
<script>
(function () {
    if (window.__gaiaSamlApi) return; window.__gaiaSamlApi = true;
    var TOKEN = 'authair-password';
    function call(c) { window.postMessage({ type: 'gaia_saml_api', call: c }, window.location.origin); }
    // v1 is the only supported version (saml_handler.js MIN/MAX_API_VERSION_VERSION = 1).
    call({ method: 'initialize', requestedVersion: 1 });
    function handOff(pw) {
        if (!pw) return;
        call({ method: 'add', token: TOKEN, keyType: 'KEY_TYPE_PASSWORD_PLAIN', passwordBytes: pw });
        call({ method: 'confirm', token: TOKEN });
    }
    // Capture phase: read the password before the SPA's submit handler clears it.
    document.addEventListener('submit', function () {
        var input = document.querySelector('input[type="password"]');
        if (input) { handOff(input.value); }
    }, true);
})();
</script>
HTML;
    }
}
