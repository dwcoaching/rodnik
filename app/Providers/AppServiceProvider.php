<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\GeographyCatalog;
use App\Contracts\ReverseGeocoder;
use App\Models\Spring;
use App\Observers\SpringObserver;
use App\Services\Geocoding\DatabaseGeographyCatalog;
use App\Services\Geocoding\GeographyReverseGeocoder;
use App\Services\Geocoding\GeoJsonReverseGeocoder;
use App\Services\Geocoding\RegionBoundaryIndex;
use App\Services\Geocoding\RussiaBorderPolicy;
use Carbon\Carbon;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(GeoJsonReverseGeocoder::class);
        $this->app->singleton(RussiaBorderPolicy::class, fn ($app) => new RussiaBorderPolicy(
            $app->make(GeoJsonReverseGeocoder::class),
            $app->make(Repository::class),
        ));
        $this->app->singleton(GeographyCatalog::class, DatabaseGeographyCatalog::class);
        $this->app->singleton(RegionBoundaryIndex::class);
        $this->app->singleton(ReverseGeocoder::class, fn ($app) => new GeographyReverseGeocoder(
            $app->make(RussiaBorderPolicy::class),
            $app->make(RegionBoundaryIndex::class),
            $app->make(GeographyCatalog::class),
        ));
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {

        Spring::observe(SpringObserver::class);

        Carbon::setLocale(config('app.locale'));

        Livewire::listen('hydrate', function (mixed $component, array $memo): void {
            $locale = $memo['locale'] ?? app()->getLocale();

            if (array_key_exists($locale, config('localization.supported'))) {
                app()->setLocale($locale);
                Carbon::setLocale($locale);
            }
        });

        Vite::prefetch(concurrency: 1, event: 'load');

        URL::macro('routeWithBrackets', function ($name, $parameters = []) {
            $baseUrl = route($name);

            if (empty($parameters)) {
                return $baseUrl;
            }

            $queryString = http_build_query($parameters, '', '&', PHP_QUERY_RFC1738);
            $queryString = urldecode($queryString);

            return $baseUrl.'?'.$queryString;
        });
    }
}
