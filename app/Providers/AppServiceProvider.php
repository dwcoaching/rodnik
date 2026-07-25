<?php

declare(strict_types=1);

namespace App\Providers;

use Carbon\Carbon;
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
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {

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
