<?php

use App\Http\Controllers\ForwardAuth\ForwardAuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Forward-auth routes (Authentik-outpost compatible)
|--------------------------------------------------------------------------
|
| Root-relative endpoint a reverse proxy (Traefik forwardAuth, nginx
| auth_request, Caddy forward_auth) hits as a subrequest to decide whether a
| request to a containerized app is allowed. The path and X-authentik-* response
| header names deliberately match Authentik's nginx outpost so an existing
| forwardAuth config only needs its address repointed at aut.hair.
|
| Registered with NO prefix (like the GAIA routes) so the path resolves at the
| domain root. The `web` middleware is attached per-route so StartSession reads
| the session cookie; the verify handler inspects auth state and returns a status
| rather than throwing, so it must NOT use the `auth` middleware.
*/

Route::middleware(['web', 'throttle:forward-auth'])
    ->get('outpost.goauthentik.io/auth/nginx', [ForwardAuthController::class, 'verify'])
    ->name('verify');
