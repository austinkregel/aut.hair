<?php

use App\Http\Controllers\AccessTokenController;
use App\Http\Controllers\Gaia\UserinfoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| GAIA-compat routes (openFyde / ChromeOS sign-in)
|--------------------------------------------------------------------------
|
| openFyde's Chromium speaks Google's GAIA protocol, repointed at aut.hair via
| --fydeos-gaia-url / --fydeos-apis-url. These root-relative endpoints mirror
| the GAIA endpoints Chromium calls; most are thin adapters over aut.hair's
| existing Passport/OIDC machinery. Registered with NO prefix in
| RouteServiceProvider (GAIA paths are root-relative). See the fyde-fork repo's
| docs/gaia-shim-spike.md for the full surface.
|
| This file is the token half (device-independent). The sign-in webview page
| (embedded/setup/v2/chromeos) lands in a later slice.
*/

// Token endpoint (apis origin) — authorization_code and refresh_token grants.
// Reuses aut.hair's customized Passport token controller unchanged: Chromium
// POSTs grant_type/code/client_id/client_secret/scope like any OAuth2 client,
// and issueToken already handles client_secret_basic/post.
Route::post('oauth2/v4/token', [AccessTokenController::class, 'issueToken'])
    ->name('token')
    ->middleware('throttle');

// UserInfo (apis origin) — the GAIA shape Chromium parses: {id, email,
// verified_email}. `id` is the stable gaia id (must match the obfuscatedid
// emitted in the google-accounts-signin header at sign-in). We deliberately
// OMIT `hostedDomain` so Chromium treats this as a consumer account and skips
// enterprise device-management enrollment.
Route::middleware('auth:api')
    ->get('oauth2/v1/userinfo', UserinfoController::class)
    ->name('userinfo');
