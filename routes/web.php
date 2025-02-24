<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SpringController;
use Bugsnag\BugsnagLaravel\Facades\Bugsnag;
use App\Http\Controllers\CoverageController;
use App\Http\Controllers\SpringJsonController;
use App\Http\Controllers\PhotosBatchController;
use App\Http\Controllers\SpringHistoryController;
use App\Http\Controllers\SpringLocationController;
use App\Http\Controllers\SpringTileJsonController;
use App\Http\Controllers\UserSpringsJsonController;
use App\Http\Controllers\Stats\MoscowStatsController;
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

Route::get('/', [WebController::class, 'index'])->name('duo');
/* redirect */ Route::get('/create', [SpringController::class, 'create'])->name('springs.create');
/* redirect */ Route::get('/{springId}', [SpringController::class, 'show'])->name('springs.show')->where('springId', '[0-9]+');
/* redirect */ Route::get('/{spring}/location/edit', [SpringLocationController::class, 'edit'])->name('springs.location.edit')->where('spring', '[0-9]+');
/* redirect */ Route::get('/users/{userId}', [WebController::class, 'user'])->name('users.show')->where('userId', '[0-9]+');

Route::get('/{spring}/edit', [SpringController::class, 'edit'])->name('springs.edit')->where('spring', '[0-9]+');
Route::get('/{spring}/history', [SpringHistoryController::class, 'index'])->name('springs.history')->where('spring', '[0-9]+');

Route::resource('reports', ReportController::class);

Route::get('photos/create', [PhotosBatchController::class, 'create']);

Route::get('springs.json', [SpringJsonController::class, 'index']);
Route::get('spring-aggregates.json', [SpringAggregatesJsonController::class, 'index']);
Route::get('tiles/{z}/{x}/{y}.json', [SpringTileJsonController::class, 'show']);
Route::get('watered-tiles/{z}/{x}/{y}.json', [WateredSpringTileJsonController::class, 'show']);
Route::get('users/{user}/springs.json', [UserSpringsJsonController::class, 'index']);

Route::get('overpass-batches/{overpassBatch}/coverage', [CoverageController::class, 'index'])->name('coverage');

Route::get('moscow-stats', MoscowStatsController::class)->name('moscow-stats');

// Keep only as an example
// Route::get('/about', About::class);

Route::get('bugsnag-test', function() {
    Bugsnag::notifyException(new \RuntimeException("New Test Error"));
});

// TODO

/*

1.  Теги, карты по ссылкой

2.  Репорты из видимой области карты

3.  Бюро

4.  Формирование файлов OSM для JOSM из бюро

5.  «Всё — репорт», переход на этот принцип, где информация
    Родника четко отличается от информации из внешних источников.


Переход на принцип «всё — репорт» — это классный пример работы с историей.
OSM — это окончание цельного проекта. (Другие источники — это блажь,
можно и не делать.)






**********
Андрей Лазарев, [20 Feb 2025 at 09:35:45 (20 Feb 2025 at 09:41:06)]:
Объединить пожалуйста:

1.
https://rodnik.today/1140401
https://rodnik.today/868344

2.
https://rodnik.today/902617
https://rodnik.today/868345


Gregory M, [20 Feb 2025 at 09:37:38]:
тоже можно объединить, пожалуйста:
https://rodnik.today/?s=164079&u=0
https://rodnik.today/?s=983711&u=0



*/
