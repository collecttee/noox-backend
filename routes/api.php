<?php

use Illuminate\Http\Request;

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
Route::get('/test', 'TestController@test');
Route::prefix('v1')->group(function () {
    Route::get('/sign/{address}', 'AuthController@sign');
    Route::get('/verify', 'AuthController@verify');
    Route::get('/check', 'BadgeController@check');
    Route::get('/issuingSignature/{badgeId}', 'BadgeController@issuingSignature');
    Route::post('/badge', 'BadgeController@create');
});

