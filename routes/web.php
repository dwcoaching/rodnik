<?php

declare(strict_types=1);

use App\Http\Controllers\CoverageController;
use App\Http\Controllers\HeatmapController;
use App\Http\Controllers\LatestExportController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\PhotoUploadController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\SpringAggregatesJsonController;
use App\Http\Controllers\SpringController;
use App\Http\Controllers\SpringHistoryController;
use App\Http\Controllers\SpringTileJsonController;
use App\Http\Controllers\Stats\MoscowStatsController;
use App\Http\Controllers\Tools\EnrichedGPXController;
use App\Http\Controllers\TrackController;
use App\Http\Controllers\TrackPolygonController;
use App\Http\Controllers\UserPhotoController;
use App\Http\Controllers\UserSpringsJsonController;
use App\Http\Controllers\WateredSpringTileJsonController;
use App\Http\Controllers\WebController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;

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

Route::post('locale/{locale}', LocaleController::class)->name('locale.update');

Route::get('sitemap.xml', [SitemapController::class, 'index'])->name('sitemap.index');
Route::get('sitemaps/static.xml', [SitemapController::class, 'staticPages'])->name('sitemap.static');
Route::get('sitemaps/springs-{page}.xml', [SitemapController::class, 'springs'])
    ->whereNumber('page')
    ->name('sitemap.springs');

$resourceRoutes = function (): void {
    Route::get('/', [WebController::class, 'index'])->name('duo');
    Route::get('/create', [WebController::class, 'index'])->defaults('location', 1)->name('springs.create');
    Route::get('/{springId}', [WebController::class, 'index'])->whereNumber('springId')->name('springs.show');
    Route::get('/users/{userId}', [WebController::class, 'index'])->whereNumber('userId')->name('users.show');
    Route::get('/{spring}/location/edit', [WebController::class, 'index'])
        ->whereNumber('spring')->defaults('location', 1)->name('springs.location.edit');

    Route::get('/springs/create', [WebController::class, 'index'])->defaults('location', 1);
    Route::get('/springs/{springId}', [WebController::class, 'index'])->whereNumber('springId');
    Route::get('/springs/{springId}/location/edit', [WebController::class, 'index'])
        ->whereNumber('springId')->defaults('location', 1);
    Route::get('/springs/{springId}/{section}', [WebController::class, 'legacySection'])
        ->whereNumber('springId')->whereIn('section', ['edit', 'history']);
};

$publicRoutes = function () use ($resourceRoutes): void {
    $resourceRoutes();

    Route::get('maps/{map:slug}', [MapController::class, 'show'])
        ->where('map', '[a-zA-Z0-9-]{3,80}')
        ->missing(fn (Request $request): Response|JsonResponse => app(MapController::class)->missing($request))
        ->name('maps.show');
    Route::get('maps/{map:slug}/login', [MapController::class, 'login'])
        ->where('map', '[a-zA-Z0-9-]{3,80}')
        ->name('maps.login');
    Route::get('user/tracks/options', [TrackController::class, 'options'])
        ->middleware('auth')->name('tracks.options');
    Route::get('user/tracks', [MapController::class, 'index'])
        ->middleware('auth')->name('tracks.index');
    Route::patch('user/tracks/{track:token}', [TrackController::class, 'update'])
        ->where('track', '[a-zA-Z0-9]{10}')->middleware(['auth', 'throttle:30,1,tracks-update'])->name('tracks.update');
    Route::delete('user/tracks/{track:token}', [TrackController::class, 'destroy'])
        ->where('track', '[a-zA-Z0-9]{10}')->middleware(['auth', 'throttle:30,1,tracks-delete'])->name('tracks.destroy');
    Route::get('user/tracks/{track:token}/download', [TrackController::class, 'download'])
        ->where('track', '[a-zA-Z0-9]{10}')->middleware('auth')->name('tracks.download');

    Route::get('user/maps/check-slug', [MapController::class, 'checkSlug'])
        ->middleware(['auth', 'throttle:60,1,maps-slug'])->name('maps.check-slug');
    Route::get('user/maps/options', [MapController::class, 'options'])
        ->middleware('auth')->name('maps.options');
    Route::get('user/maps', [MapController::class, 'index'])
        ->middleware('auth')
        ->name('maps.index');
    Route::get('user/maps/{map:id}/edit', [MapController::class, 'edit'])
        ->whereNumber('map')->middleware('auth')->name('maps.edit');
    Route::patch('user/maps/{map:id}/star', [MapController::class, 'star'])
        ->whereNumber('map')->middleware(['auth', 'throttle:60,1,maps-star'])->name('maps.star');
    Route::delete('user/maps/{map:id}', [MapController::class, 'destroy'])
        ->whereNumber('map')->middleware(['auth', 'throttle:30,1,maps-delete'])->name('maps.destroy');

    Route::resource('users.photos', UserPhotoController::class)->only('index');

    Route::get('/{spring}/edit', [SpringController::class, 'edit'])->name('springs.edit')->where('spring', '[0-9]+');
    Route::get('/{spring}/history', [SpringHistoryController::class, 'index'])->name('springs.history')->where('spring', '[0-9]+');

    Route::resource('reports', ReportController::class);

    Route::get('moscow-stats', MoscowStatsController::class)->name('moscow-stats');

    Route::get('tools/enrich', [EnrichedGPXController::class, 'create'])->name('tools.enriched-gpx');
    Route::post('tools/enrich', [EnrichedGPXController::class, 'store'])->name('tools.enriched-gpx.store');
};

Route::middleware('set-locale:en')->group($publicRoutes);

Route::prefix('ru')
    ->name('ru.')
    ->middleware('set-locale:ru')
    ->group($publicRoutes);

Route::prefix('en')
    ->name('en.')
    ->middleware('set-locale:en')
    ->group($resourceRoutes);

Route::get('docs/exports/{format}/latest', LatestExportController::class)
    ->whereIn('format', array_keys(LatestExportController::FORMATS))
    ->name('exports.latest');

Route::get('overpass-batches/{overpassBatch}/coverage', [CoverageController::class, 'index'])->name('coverage');
Route::get('heatmap', [HeatmapController::class, 'index'])->name('heatmap');

Route::post('photos/uploads', [PhotoUploadController::class, 'store'])->name('photos.uploads.store');
Route::delete('photos/uploads/{photo}', [PhotoUploadController::class, 'destroy'])->name('photos.uploads.destroy');

Route::post('maps', [MapController::class, 'store'])
    ->middleware(['auth', 'throttle:20,1,maps-create'])
    ->name('maps.store');
Route::patch('maps/{map:id}', [MapController::class, 'update'])
    ->whereNumber('map')
    ->middleware(['auth', 'throttle:30,1,maps-update'])
    ->name('maps.update');
Route::patch('maps/{map:id}/title', [MapController::class, 'rename'])
    ->whereNumber('map')
    ->middleware(['auth', 'throttle:30,1,maps-update'])
    ->name('maps.rename');
Route::patch('maps/{map:id}/details', [MapController::class, 'details'])
    ->whereNumber('map')
    ->middleware(['auth', 'throttle:30,1,maps-update'])
    ->name('maps.details');

Route::get('tracks/{token}', [TrackController::class, 'show'])
    ->where('token', '[a-zA-Z0-9]{10}')
    ->middleware('throttle:120,1,tracks-lookup')
    ->name('tracks.show');
Route::post('tracks', [TrackController::class, 'store'])
    ->middleware('throttle:tracks-upload')
    ->name('tracks.store');

Route::get('track-polygons/{hash}', [TrackPolygonController::class, 'show'])
    ->where('hash', '[a-f0-9]{64}')
    ->middleware('throttle:120,1,track-polygons-lookup')
    ->name('track-polygons.show');
Route::post('track-polygons', [TrackPolygonController::class, 'store'])
    ->middleware('throttle:30,1,track-polygons-upload')
    ->name('track-polygons.store');

Route::get('spring-aggregates.json', [SpringAggregatesJsonController::class, 'index']);
Route::get('tiles/{z}/{x}/{y}.json', [SpringTileJsonController::class, 'show']);
Route::get('watered-tiles/{z}/{x}/{y}.json', [WateredSpringTileJsonController::class, 'show']);
Route::get('users/{user}/springs.json', [UserSpringsJsonController::class, 'index']);
