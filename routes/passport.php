<?php

use App\Http\Controllers\AccessTokenController;
use App\Http\Controllers\OAuth\ApproveAuthorizationController;
use App\Http\Controllers\OAuth\ClientController;
use Illuminate\Support\Facades\Route;

// Kept compatible with Passport's optional guard support.
$guard = null;

Route::post('/token', [AccessTokenController::class, 'issueToken'])
    ->name('token')
    ->middleware('throttle');

Route::get('/authorize', [
    'uses' => 'AuthorizationController@authorize',
    'as' => 'authorizations.authorize',
    'middleware' => ['web', $guard ? 'auth:'.$guard : 'auth', 'oidc.auth_time', 'oauth.team'],
]);

Route::middleware(['web', $guard ? 'auth:'.$guard : 'auth', 'oidc.auth_time'])->group(function () {
    Route::post('/token/refresh', [
        'uses' => 'TransientTokenController@refresh',
        'as' => 'token.refresh',
    ]);

    Route::post('/authorize', [ApproveAuthorizationController::class, 'approve'])
        ->name('authorizations.approve')
        ->middleware('oauth.team');

    Route::delete('/authorize', [
        'uses' => 'DenyAuthorizationController@deny',
        'as' => 'authorizations.deny',
        'middleware' => ['oauth.team'],
    ]);

    Route::get('/tokens', [
        'uses' => 'AuthorizedAccessTokenController@forUser',
        'as' => 'tokens.index',
    ]);

    Route::delete('/tokens/{token_id}', [
        'uses' => 'AuthorizedAccessTokenController@destroy',
        'as' => 'tokens.destroy',
    ]);

    Route::get('/clients', [ClientController::class, 'forUser'])->name('clients.index');

    Route::post('/clients', [ClientController::class, 'store'])->name('clients.store');

    Route::put('/clients/{client_id}', [ClientController::class, 'update'])->name('clients.update');

    Route::delete('/clients/{client_id}', [ClientController::class, 'destroy'])->name('clients.destroy');

    Route::get('/scopes', [
        'uses' => 'ScopeController@all',
        'as' => 'scopes.index',
    ]);

    Route::get('/personal-access-tokens', [
        'uses' => 'PersonalAccessTokenController@forUser',
        'as' => 'personal.tokens.index',
    ]);

    Route::post('/personal-access-tokens', [
        'uses' => 'PersonalAccessTokenController@store',
        'as' => 'personal.tokens.store',
    ]);

    Route::delete('/personal-access-tokens/{token_id}', [
        'uses' => 'PersonalAccessTokenController@destroy',
        'as' => 'personal.tokens.destroy',
    ]);
});
