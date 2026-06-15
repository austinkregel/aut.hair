<?php

use App\Http\Controllers\AccessTokenController;
use App\Http\Controllers\Gaia\EmbeddedSetupController;
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
| Includes the token endpoints and the sign-in webview page
| (embedded/setup/v2/chromeos). Token redemption with a real Passport auth code
| is slice 2b.
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

// Sign-in webview page (GAIA origin) — served to the OOBE <webview>. Behind
// web/auth: an unauthenticated device hits aut.hair's normal login (creds +
// 2FA) first, then returns here. The google-accounts-signin header + oauth_code
// cookie ride on this response; the page posts userInfo -> closeView.
Route::middleware(['web', 'auth'])
    ->get('embedded/setup/v2/chromeos', EmbeddedSetupController::class)
    ->name('embedded-setup');
