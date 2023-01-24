<?php

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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware([config('jetstream.auth_session'), 'verified'])->get('userinfo', function (Request $request) {
    return \App\Http\Resources\UserResource::make($request->user());
})->name('oidc.userinfo');

Route::get('jwks', function () {
    return [
        '',
    ];
})->name('oidc.jwks');
