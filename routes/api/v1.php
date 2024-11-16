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

Route::resource('springs.photos', App\Http\Controllers\Api\V1\PhotosController::class)
    ->shallow()
    ->only('index');

Route::resource('areas', App\Http\Controllers\Api\V1\ExportedAreasController::class)
    ->only('show');
