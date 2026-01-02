<?php

use App\Http\Controllers\MachineInfoController;
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

Route::middleware(['auth:api'])->get('userinfo', UserinfoController::class)->name('oidc.userinfo');

Route::middleware([\Laravel\Passport\Http\Middleware\CheckClientCredentials::class])
    ->get('machine-info', MachineInfoController::class)
    ->name('oidc.machine_info');
