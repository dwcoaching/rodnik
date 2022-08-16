<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SpringController;
use App\Http\Controllers\CoverageController;
use App\Http\Controllers\SpringJsonController;
use App\Http\Controllers\SpringsTileJsonController;
use App\Http\Controllers\SpringAggregatesJsonController;


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

Route::get('/', [WebController::class, 'index'])->name('index');
Route::get('/{springId}', [WebController::class, 'show'])->name('springs.show')->where('springId', '[0-9]+');

Route::resource('springs', SpringController::class);




Route::get('springs.json', [SpringJsonController::class, 'index']);
Route::get('spring-aggregates.json', [SpringAggregatesJsonController::class, 'index']);
Route::get('springs/tiles/{z}/{x}/{y}.json', [SpringsTileJsonController::class, 'show']);

Route::get('coverage', [CoverageController::class, 'index']);

Route::resource('reviews', ReviewController::class);


Route::middleware(['auth:sanctum', 'verified'])->get('/dashboard', function () {
    return view('dashboard');
})->name('dashboard');
