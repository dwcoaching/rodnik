<?php

namespace App\Providers;

use Jenssegers\Date\Date;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
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
        Date::setlocale(config('app.locale'));
        
        URL::macro('routeWithBrackets', function ($name, $parameters = []) {
            $baseUrl = route($name);
            
            if (empty($parameters)) {
                return $baseUrl;
            }
            
            $queryString = http_build_query($parameters, '', '&', PHP_QUERY_RFC1738);
            $queryString = urldecode($queryString);
            
            return $baseUrl . '?' . $queryString;
        });
    }
}
