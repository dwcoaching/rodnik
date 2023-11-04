<?php

use App\Livewire\Pages\About;
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
use App\Http\Controllers\WateredSpringTileJsonController;


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

Route::get('/{springId}', [SpringController::class, 'show'])->name('springs.show')->where('springId', '[0-9]+');
Route::get('/{spring}/edit', [SpringController::class, 'edit'])->name('springs.edit')->where('spring', '[0-9]+');
Route::get('/create', [SpringController::class, 'create'])->name('springs.create');

Route::get('/users/{userId}', [WebController::class, 'user'])->name('users.show')->where('userId', '[0-9]+');

Route::get('photos/create', [PhotosBatchController::class, 'create']);

Route::get('springs.json', [SpringJsonController::class, 'index']);
Route::get('spring-aggregates.json', [SpringAggregatesJsonController::class, 'index']);
Route::get('tiles/{z}/{x}/{y}.json', [SpringTileJsonController::class, 'show']);
Route::get('watered-tiles/{z}/{x}/{y}.json', [WateredSpringTileJsonController::class, 'show']);

Route::get('users/{user}/springs.json', [UserSpringsJsonController::class, 'index']);





Route::get('overpass-batches/{overpassBatch}/coverage', [CoverageController::class, 'index'])->name('coverage');

Route::resource('reports', ReportController::class);
Route::get('/about', About::class);
