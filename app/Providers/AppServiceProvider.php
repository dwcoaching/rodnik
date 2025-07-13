<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Carbon\Carbon;
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

        Carbon::setLocale(config('app.locale'));
        
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
