<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Passport;

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

Route::middleware('auth:api')->get('userinfo', function (Request $request) {    
    $user = [];
    
    if (request->user()->tokenCan('openid')) {
        $user['id'] = auth()->id();
        $user['updated_at'] => auth()->user()->updated_at;
        $user['created_at'] => auth()->user()->created_at;
    }
    if (request->user()->tokenCan('profile')) {
        $user['photo_url'] = auth()->user()->profile_photo_url;
        $user['name'] = auth()->user()->name;
    }
        
    if (request->user()->tokenCan('email')) {
        $user['email'] = auth()->user()->email;
        $user['email_verified_at'] = auth()->user()->eamil_verified_at;
    }
    
    return response()->json($user);
})->name('oidc.userinfo');

Route::get('jwks', function () {
    return 'ok';
})->name('oidc.jwks');
