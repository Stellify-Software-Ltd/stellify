<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

use App\Http\Controllers\RouteController;

use Illuminate\Foundation\Auth\EmailVerificationRequest;
use App\Http\Middleware\mergeSettingsIntoConfig;


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
Route::get('/sitemap.xml', [RouteController::class, 'generateSitemap']);

Route::get('/run', [RouteController::class, 'run'])->middleware('web', 'config.merge');;

Route::get('/js/{all}', [RouteController::class, 'requestJavascript'])->middleware('web', 'config.merge');

Route::get('/stellify/stream/elements/{view}', [RouteController::class, 'streamElements'])->where('all', '.*')->middleware('web');

Route::post('{all}', [RouteController::class, 'index'])->where('all', '.*')->middleware('web', 'config.merge');
Route::get('{all}', [RouteController::class, 'index'])->where('all', '.*')->middleware('web', 'config.merge');
Route::put('{all}', [RouteController::class, 'index'])->where('all', '.*')->middleware('web', 'config.merge');
Route::patch('{all}', [RouteController::class, 'index'])->where('all', '.*')->middleware('web', 'config.merge');
Route::delete('{all}', [RouteController::class, 'index'])->where('all', '.*')->middleware('web', 'config.merge');
Route::options('{all}', [RouteController::class, 'index'])->where('all', '.*')->middleware('web', 'config.merge');
