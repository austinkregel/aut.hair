<?php

use App\Http\Controllers\ForwardAuth\ForwardAuthAppController;
use App\Http\Controllers\MachineInfoController;
use App\Http\Controllers\SeedReleaseController;
use App\Http\Controllers\UserinfoController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware('auth:api')->get('userinfo', UserinfoController::class)->name('oidc.userinfo');

// Releases the user's sync-chain seed (creating it on first use). Gated by the
// `sync` scope. The seed is the identity for self-hosted, client-side-encrypted
// browser/OS sync; aut.hair gates its release, not the sync wire itself.
Route::middleware('auth:api')->post('sync-seed', SeedReleaseController::class)->name('sync.seed');

Route::middleware(\Laravel\Passport\Http\Middleware\CheckClientCredentials::class)
    ->get('machine-info', MachineInfoController::class)
    ->name('oidc.machine_info');

// Deploy-time forward-auth registration (Option A). A trusted machine (e.g.
// homelab-in-a-box) upserts a protected app with a client_credentials token that
// carries the `forward-auth` scope.
Route::middleware(\Laravel\Passport\Http\Middleware\CheckClientCredentials::class.':forward-auth')
    ->post('forward-auth/apps', [ForwardAuthAppController::class, 'store'])
    ->name('forward-auth.apps.store');
