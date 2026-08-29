<?php

namespace App\Http\Controllers\ForwardAuth;

use App\Http\Controllers\Controller;
use App\Models\ProxyApp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\IpUtils;
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

        $app = ProxyApp::where('host', $host)->first()
            ?? $this->discover($request, $host);

        // Fail closed: a missing, pending, rejected, or disabled app never reaches
        // the auth checks below.
        if (! $app || ! $app->isActive()) {
            return response('Forbidden: this app is not approved for forward auth.', 403);
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
            // Only the teams that entitle THIS user to THIS app — never the user's
            // full team membership, which would leak unrelated (private) teams to
            // the app operator.
            'X-authentik-groups' => $this->groupsFor($app, $user),
        ]);
    }

    /**
     * First-contact discovery (Option B): auto-register an unknown host as a pending
     * ProxyApp for approval, still failing closed.
     *
     * Gated to trusted subnets so a random internet actor spraying fresh
     * X-Forwarded-Host values cannot flood the approval queue with junk rows, and
     * rate-limited per IP as a backstop against a spoofed trusted source. Returns
     * null when discovery is off, the caller is untrusted, or the limit is hit —
     * the caller then 403s without writing anything.
     *
     * This is the ONLY throttled path: the verify hot path for already-registered
     * apps is never rate-limited, so asset-heavy page loads are unaffected.
     */
    protected function discover(Request $request, string $host): ?ProxyApp
    {
        if (! config('forward-auth.auto_discovery') || ! $this->fromTrustedSubnet($request)) {
            Log::warning('forward-auth: unregistered host, discovery skipped', [
                'host' => $host,
                'ip' => $request->ip(),
            ]);

            return null;
        }

        $limit = (int) config('forward-auth.discovery_throttle', 20);
        if (RateLimiter::tooManyAttempts('fa-discovery:'.$request->ip(), $limit)) {
            Log::warning('forward-auth: discovery rate limit hit', [
                'host' => $host,
                'ip' => $request->ip(),
            ]);

            return null;
        }
        RateLimiter::hit('fa-discovery:'.$request->ip(), 60);

        // firstOrCreate dedupes on the unique host under concurrent first hits.
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

        return $app;
    }

    protected function fromTrustedSubnet(Request $request): bool
    {
        $subnets = config('forward-auth.trusted_subnets', []);

        return ! empty($subnets) && IpUtils::checkIp((string) $request->ip(), $subnets);
    }

    /**
     * The user's team ids intersected with the teams entitled to this app, pipe-joined.
     */
    protected function groupsFor(ProxyApp $app, $user): string
    {
        return $user->allTeams()
            ->pluck('id')
            ->intersect($app->allowedTeamIds())
            ->unique()
            ->values()
            ->implode('|');
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
