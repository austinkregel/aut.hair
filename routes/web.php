<?php

use App\Models\Social;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Socialite\Facades\Socialite;
use Spatie\Activitylog\Models\Activity;
use Spatie\QueryBuilder\QueryBuilder;
use App\Http\Controllers;

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

Route::get('/.well-known/openid-configuration', Controllers\WellKnownController::class);
Route::get('/oauth/jwks', Controllers\JsonWebKeysController::class)->name('oauth.jwks');

Route::middleware([config('jetstream.auth_session'), 'verified'])->group(function () {
    Route::get('/api/social-accounts', function (Request $request) {
        return $request->user()->socials;
    });
    // Users must be verified before they can actually login or link an oauth provider.
    Route::prefix('/login/{provider}')->group(function () {
//        Route::get('/team', Controllers\Auth\Team\RedirectController::class);
        Route::get('/', Controllers\Auth\RedirectController::class);
    });
    Route::prefix('/callback/{provider}')->group(function () {
//        Route::get('/team', Controllers\Auth\Team\CallbackController::class);
        Route::get('/', Controllers\Auth\CallbackController::class);
    });

    Route::get('/', Controllers\DashboardController::class.'@link');
    Route::get('/dashboard', Controllers\DashboardController::class)->name('dashboard');

    Route::get('/user/oauth', Controllers\Settings\OauthLinkController::class)->name('oauth.link');
    Route::get('/user/admin', Controllers\Settings\AdminController::class);
});




