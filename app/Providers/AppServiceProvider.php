<?php

declare(strict_types=1);

namespace App\Providers;

use Carbon\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

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

        if (config('app.force_http')) {
            URL::forceScheme('http');
        }

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
