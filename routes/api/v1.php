<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthTokenController;
use App\Http\Controllers\Api\V1\CurrentUserController;
use App\Http\Controllers\Api\V1\ExportedAreasController;
use App\Http\Controllers\Api\V1\PhotoController;
use App\Http\Controllers\Api\V1\RegistrationController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\ReportPhotoController;
use App\Http\Controllers\Api\V1\SpringController;
use App\Http\Controllers\Api\V1\UserReportController;
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

Route::post('auth/token', [AuthTokenController::class, 'store'])
    ->middleware('throttle:api-token')
    ->name('api.v1.auth.token.store');

Route::post('auth/register', [RegistrationController::class, 'store'])
    ->middleware('throttle:api-register')
    ->name('api.v1.auth.register');

Route::get('springs/{spring}', [SpringController::class, 'show'])
    ->name('api.v1.springs.show');

Route::get('users/{user}/reports', [UserReportController::class, 'index'])
    ->whereNumber('user')
    ->name('api.v1.users.reports.index');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::delete('auth/token', [AuthTokenController::class, 'destroy'])
        ->name('api.v1.auth.token.destroy');
    Route::get('me', [CurrentUserController::class, 'show'])
        ->name('api.v1.me.show');
    Route::delete('me', [CurrentUserController::class, 'destroy'])
        ->name('api.v1.me.destroy');

    Route::post('springs', [SpringController::class, 'store'])
        ->middleware('throttle:api-springs')
        ->name('api.v1.springs.store');

    Route::post('reports', [ReportController::class, 'store'])
        ->middleware('throttle:api-reports')
        ->name('api.v1.reports.store');
    Route::patch('reports/{report}', [ReportController::class, 'update'])
        ->name('api.v1.reports.update');
    Route::delete('reports/{report}', [ReportController::class, 'destroy'])
        ->name('api.v1.reports.destroy');

    Route::post('reports/{report}/photos', [ReportPhotoController::class, 'store'])
        ->name('api.v1.reports.photos.store');
    Route::delete('photos/{photo}', [PhotoController::class, 'destroy'])
        ->name('api.v1.photos.destroy');
});

Route::resource('areas', ExportedAreasController::class)
    ->only('show');
