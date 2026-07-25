<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Middleware\SetLocale;
use Illuminate\Support\ServiceProvider;
use Laravel\Folio\Folio;

final class FolioServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Folio::path(resource_path('views/pages'))->middleware([
            '*' => [
                SetLocale::class.':en',
            ],
        ])->uri('/docs');

        Folio::path(resource_path('views/pages-ru'))->middleware([
            '*' => [
                SetLocale::class.':ru',
            ],
        ])->uri('/ru/docs');
    }
}
