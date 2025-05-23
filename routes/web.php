<?php

use App\Http\Controllers;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::middleware('web')->group(function () {
    Route::prefix('/callback/{provider}')->group(function () {
        Route::get('/team', Controllers\Auth\Team\CallbackController::class);
        Route::get('/', Controllers\Auth\CallbackController::class);
    });
    Route::get('/.well-known/openid-configuration', Controllers\WellKnownController::class)->name('well-known');
    Route::get('/oauth/jwks', Controllers\JsonWebKeysController::class)->name('oidc.jwks');
// OIDC End Session Endpoint
    Route::match(['GET', 'POST'], '/oauth/logout', Controllers\OidcLogoutController::class)->name('oauth.logout');
    Route::post('/oauth/revoke', App\Http\Controllers\OidcTokenRevocationController::class)->name('oauth.revoke');
    Route::get('/api/available-login-providers', Controllers\AvailableLoginProvidersController::class);
});

Route::middleware([config('jetstream.auth_session'), 'verified'])->group(function () {
    Route::get('/api/social-accounts', function (Request $request) {
        return $request->user()->socials;
    });
    // Users must be verified before they can actually login or link an oauth provider.
    Route::prefix('/login/{provider}')->group(function () {
        Route::get('/team', Controllers\Auth\Team\RedirectController::class);
        Route::get('/', Controllers\Auth\RedirectController::class);
    });

    Route::get('/', Controllers\DashboardController::class.'@link')->name('issuer');
    Route::get('/dashboard', Controllers\DashboardController::class)->name('dashboard');

    Route::get('/user/oauth', Controllers\Settings\OauthLinkController::class)->name('oauth.link');
    Route::delete('/user/oauth/remove', Controllers\Settings\RemoveOauthLinkController::class)->name('oauth.link.remove');
});

Route::middleware([config('jetstream.auth_session'), 'verified', App\Http\Middleware\OnlyHost::class])->group(function () {
    Route::get('/user/admin', Controllers\Settings\AdminController::class)->name('admin');
    Route::post('/api/install', Controllers\InstallNewProvider::class);
    Route::post('/api/uninstall', Controllers\UninstallNewProvider::class);
    Route::post('/api/enable', Controllers\EnableProviderController::class);
    Route::post('/api/disable', Controllers\DisableProviderController::class);
});
