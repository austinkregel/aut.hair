<?php

namespace App\Http\Controllers\ForwardAuth;

use App\Http\Controllers\Controller;
use App\Models\ProxyApp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authentik-outpost compatible forward-auth endpoint.
 *
 * Traefik's `forwardAuth` middleware (with trustForwardHeader: true) fires a
 * subrequest here on every request to an sso_protected app, forwarding the
 * browser's Cookie plus X-Forwarded-Proto/Host/Uri/Method. We read aut.hair's
 * own session cookie (this route runs in the `web` group, so StartSession has
 * populated it) and answer:
 *
 *   - 200  session valid AND the user's team is entitled to this host. Traefik
 *          copies the X-authentik-* response headers onto the upstream request.
 *   - 302  no session — redirect the browser to aut.hair login, then back to the
 *          originally requested URL.
 *   - 403  unregistered host (fail closed) OR authenticated-but-not-entitled.
 *
 * The endpoint is a GET so it is exempt from CSRF. It must NOT use the `auth`
 * middleware: that throws AuthenticationException instead of letting us return a
 * status, the same reason EmbeddedSetupController avoids it.
 */
class ForwardAuthController extends Controller
{
    public function verify(Request $request): Response
    {
        // X-Forwarded-Host/Proto are trusted headers (see TrustProxies) and are
        // reflected by getHost()/getScheme(). X-Forwarded-Uri/Method are NOT in
        // Symfony's trusted set, so read them off the raw header.
        $host = $request->header('X-Forwarded-Host', $request->getHost());

        // First-contact discovery (Option B): an unknown host is auto-registered as
        // pending and surfaced for approval. firstOrCreate dedupes on the unique
        // host, so repeat traffic never floods the queue. Fail closed either way —
        // a pending, rejected, or disabled app never reaches the auth checks below.
        $app = ProxyApp::firstOrCreate(
            ['host' => $host],
            [
                'name' => $host,
                'status' => ProxyApp::STATUS_PENDING,
                'enabled' => false,
                'discovered_at' => now(),
                'requested_by' => $request->user()?->id,
            ]
        );

        if ($app->wasRecentlyCreated) {
            Log::warning('forward-auth: discovered new host, pending approval', [
                'host' => $host,
                'proxy_app_id' => $app->id,
            ]);
        }

        if (! $app->isActive()) {
            return response('Forbidden: this app is not approved for forward auth ('.$app->status.').', 403);
        }

        $user = $request->user();

        if (! $user) {
            $request->session()->put('url.intended', $this->returnUrl($request, $host));

            return redirect()->to($this->loginUrl());
        }

        if (! $app->allowsUser($user)) {
            return response('Forbidden: you do not have access to this app.', 403);
        }

        return response('', 200, [
            'X-authentik-username' => $user->email,
            'X-authentik-email' => $user->email,
            'X-authentik-groups' => $user->allTeams()->unique('id')->pluck('id')->implode('|'),
        ]);
    }

    /**
     * Reconstruct the originally requested URL from the forwarded headers.
     *
     * Built server-side from X-Forwarded-Proto/Host/Uri — never from a
     * client-supplied redirect param. Because $host has already been resolved to
     * a registered ProxyApp, the result can only point at a known protected host,
     * so this cannot be used as an open redirector.
     */
    protected function returnUrl(Request $request, string $host): string
    {
        $proto = $request->header('X-Forwarded-Proto', $request->getScheme());
        $uri = $request->header('X-Forwarded-Uri', '/');

        if (! str_starts_with($uri, '/')) {
            $uri = '/'.$uri;
        }

        return $proto.'://'.$host.$uri;
    }

    /**
     * aut.hair's own login URL.
     *
     * Built from config('app.url'), NOT route('login'): Traefik forwards the
     * protected app's host as X-Forwarded-Host (trustForwardHeader), which
     * poisons the URL generator's host — route('login') would point at the app,
     * which does not serve login. The IdP login lives at aut.hair's canonical URL.
     */
    protected function loginUrl(): string
    {
        return rtrim(config('app.url'), '/').route('login', absolute: false);
    }
}
