<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SpringController;
use App\Http\Controllers\CoverageController;
use App\Http\Controllers\SpringJsonController;
use App\Http\Controllers\PhotosBatchController;
use App\Http\Controllers\SpringTileJsonController;
use App\Http\Controllers\UserSpringsJsonController;
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
Route::get('/{springId}', [WebController::class, 'show'])->name('show')->where('springId', '[0-9]+');
Route::get('/users/{userId}', [WebController::class, 'user'])->name('user')->where('userId', '[0-9]+');


Route::get('/create', [SpringController::class, 'create'])->name('springs.create');
Route::get('/{spring}/edit', [SpringController::class, 'edit'])->name('springs.edit')->where('spring', '[0-9]+');
Route::get('photos/create', [PhotosBatchController::class, 'create']);




Route::get('springs.json', [SpringJsonController::class, 'index']);
Route::get('spring-aggregates.json', [SpringAggregatesJsonController::class, 'index']);
Route::get('tiles/{z}/{x}/{y}.json', [SpringTileJsonController::class, 'show']);

Route::get('users/{user}/springs.json', [UserSpringsJsonController::class, 'index']);





Route::get('coverage', [CoverageController::class, 'index']);

Route::resource('reports', ReportController::class);


Route::middleware(['auth:sanctum', 'verified'])->get('/dashboard', function () {
    return view('dashboard');
})->name('dashboard');
