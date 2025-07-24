<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SpringController;
use App\Http\Controllers\CoverageController;
use App\Http\Controllers\SpringJsonController;
use App\Http\Controllers\PhotosBatchController;
use App\Http\Controllers\SpringHistoryController;
use App\Http\Controllers\SpringLocationController;
use App\Http\Controllers\SpringTileJsonController;
use App\Http\Controllers\UserSpringsJsonController;
use App\Http\Controllers\Stats\MoscowStatsController;
use App\Http\Controllers\Tools\EnrichedGPXController;
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

Route::get('tools/enrich', [EnrichedGPXController::class, 'create'])->name('tools.enriched-gpx');
Route::post('tools/enrich', [EnrichedGPXController::class, 'store'])->name('tools.enriched-gpx.store');

// Keep only as an example
// Route::get('/about', About::class);


// TODO

// Cделать удаление источника автором, если там пока нет отчетов

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



Андрей Лазарев, [14 Jul 2025 at 14:14:55]:
Вообще человеку с улицы в первый раз зашедшему на сайт мало что понятно что есть что, нет легенды никакой
Ну и да классно было бы отличать иконками фонтаны от родников
Родник / колодец / фонтан
Oleg Kainov, [14 Jul 2025 at 14:16:41]:
А, фильтры не заметил вообще сначала. Тогда да, наверное больше именно вопрос к first-time UX - если я представитель целевой аудитории, которая заходит на сайт с вопросом "где источник питьевой воды неподалеку\на планируемом маршруте" - то сейчас карта сайта по умолчанию (без фильтров) на этот вопрос не отвечает никак.
Может, вместо цветных кружочков иконки какие-нибудь, да и размером поменьше, не знаю...
Андрей Лазарев, [14 Jul 2025 at 14:17:30]:
Что такое синие и красные кружочки
Что с этим делать
Где поиск как ввести свой город / местность
Куда вставить координаты
*/
