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

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware(['auth:api', 'verified'])->get('userinfo', function (Request $request) {
    return \App\Http\Resources\UserResource::make($request->user());
})->name('oidc.userinfo');

Route::get('jwks', function () {
    return [
        '',
    ];
})->name('oidc.jwks');
