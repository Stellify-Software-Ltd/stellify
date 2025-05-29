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

Route::group(['middleware' => ['auth:sanctum']], function () {
    Route::get('{all}', [RouteController::class, 'api'])->where('all', '.*');
    Route::post('{all}', [RouteController::class, 'api'])->where('all', '.*');
    Route::put('{all}', [RouteController::class, 'api'])->where('all', '.*');
    Route::delete('{all}', [RouteController::class, 'api'])->where('all', '.*');
    Route::patch('{all}', [RouteController::class, 'api'])->where('all', '.*');
    Route::options('{all}', [RouteController::class, 'api'])->where('all', '.*');
});
