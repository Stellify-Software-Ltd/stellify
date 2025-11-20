<?php

use Illuminate\Foundation\Application;

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AppController;
use App\Http\Controllers\SitemapController;


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

require __DIR__.'/auth.php';

Route::get('/sitemap.xml', [SitemapController::class, 'generateSitemap']);
Route::post('{all}', [AppController::class, 'index'])->where('all', '.*');
Route::get('{all}', [AppController::class, 'index'])->where('all', '.*');
