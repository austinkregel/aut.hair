<?php

use App\Http\Controllers\AccessTokenController;
use App\Http\Controllers\MachineTokenController;
use Illuminate\Support\Facades\Route;

Route::post('/token', [AccessTokenController::class, 'issueToken'])
    ->name('token')
    ->middleware('throttle');

Route::get('/authorize', [
    'uses' => 'AuthorizationController@authorize',
    'as' => 'authorizations.authorize',
    'middleware' => ['web', 'oidc.auth_time'],
]);

$guard = null;

Route::middleware(['web', $guard ? 'auth:'.$guard : 'auth', 'oidc.auth_time'])->group(function () {
    Route::post('/token/refresh', [
        'uses' => 'TransientTokenController@refresh',
        'as' => 'token.refresh',
    ]);

    Route::post('/authorize', [
        'uses' => 'ApproveAuthorizationController@approve',
        'as' => 'authorizations.approve',
    ]);

    Route::delete('/authorize', [
        'uses' => 'DenyAuthorizationController@deny',
        'as' => 'authorizations.deny',
    ]);

    Route::get('/tokens', [
        'uses' => 'AuthorizedAccessTokenController@forUser',
        'as' => 'tokens.index',
    ]);

    Route::delete('/tokens/{token_id}', [
        'uses' => 'AuthorizedAccessTokenController@destroy',
        'as' => 'tokens.destroy',
    ]);

    Route::get('/clients', [
        'uses' => 'ClientController@forUser',
        'as' => 'clients.index',
    ]);

    Route::post('/clients', [
        'uses' => 'ClientController@store',
        'as' => 'clients.store',
    ]);

    Route::put('/clients/{client_id}', [
        'uses' => 'ClientController@update',
        'as' => 'clients.update',
    ]);

    Route::delete('/clients/{client_id}', [
        'uses' => 'ClientController@destroy',
        'as' => 'clients.destroy',
    ]);

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

    Route::get('/machine-tokens/clients', [MachineTokenController::class, 'clients'])
        ->name('machine.tokens.clients');

    Route::post('/machine-tokens/generate', [MachineTokenController::class, 'generate'])
        ->name('machine.tokens.generate');

    Route::get('/machine-tokens/{client_id}/tokens', [MachineTokenController::class, 'tokens'])
        ->name('machine.tokens.index');

    Route::delete('/machine-tokens/tokens/{token_id}', [MachineTokenController::class, 'revoke'])
        ->name('machine.tokens.destroy');
});
