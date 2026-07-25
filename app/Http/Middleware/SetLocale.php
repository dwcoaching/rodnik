<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

final class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, ?string $forcedLocale = null): Response
    {
        $locale = $forcedLocale ?? $this->resolveLocale($request);

        abort_unless(array_key_exists($locale, config('localization.supported')), 404);

        $previousAppLocale = App::getLocale();
        $previousCarbonLocale = Carbon::getLocale();

        App::setLocale($locale);
        Carbon::setLocale($locale);
        $request->setLocale($locale);

        try {
            return $next($request);
        } finally {
            App::setLocale($previousAppLocale);
            Carbon::setLocale($previousCarbonLocale);
        }
    }

    private function resolveLocale(Request $request): string
    {
        $supportedLocales = array_keys(config('localization.supported'));
        $route = $request->route();
        if ($this->isLocalizedPublicRoute($request, $route)) {
            $urlLocale = $this->publicUrlLocale($request, $route);

            if ($urlLocale === config('localization.default')) {
                $this->suggestPreferredLocale($request, $supportedLocales);
            }

            return $urlLocale;
        }

        $userLocale = $request->user()?->locale;

        if (is_string($userLocale) && in_array($userLocale, $supportedLocales, true)) {
            return $userLocale;
        }

        $cookieLocale = $request->cookie(config('localization.cookie'));

        if (is_string($cookieLocale) && in_array($cookieLocale, $supportedLocales, true)) {
            return $cookieLocale;
        }

        $headerLocale = $request->header('X-Rodnik-Locale');

        if (is_string($headerLocale) && in_array($headerLocale, $supportedLocales, true)) {
            return $headerLocale;
        }

        return $request->getPreferredLanguage($supportedLocales)
            ?? config('localization.default');
    }

    private function isLocalizedPublicRoute(Request $request, mixed $route): bool
    {
        if ($route instanceof Route && $route->getName()) {
            $routeName = str_starts_with($route->getName(), 'ru.')
                ? mb_substr($route->getName(), 3)
                : $route->getName();

            if (\Illuminate\Support\Facades\Route::has($routeName)
                && \Illuminate\Support\Facades\Route::has('ru.'.$routeName)) {
                return true;
            }
        }

        if ($route instanceof Route && in_array('locale', $route->parameterNames(), true)) {
            return true;
        }

        return $request->is('docs')
            || $request->is('docs/*')
            || $request->is('ru/docs')
            || $request->is('ru/docs/*');
    }

    private function publicUrlLocale(Request $request, mixed $route): string
    {
        if ($route instanceof Route && is_string($route->getName())) {
            foreach (array_keys(config('localization.supported')) as $locale) {
                if ($locale !== config('localization.default') && str_starts_with($route->getName(), $locale.'.')) {
                    return $locale;
                }
            }
        }

        foreach (array_keys(config('localization.supported')) as $locale) {
            if ($locale !== config('localization.default') && $request->is($locale, $locale.'/*')) {
                return $locale;
            }
        }

        return config('localization.default');
    }

    /**
     * @param  array<int, string>  $supportedLocales
     */
    private function suggestPreferredLocale(Request $request, array $supportedLocales): void
    {
        if ($request->user()?->locale || $request->hasCookie(config('localization.cookie'))) {
            return;
        }

        $preferredLocale = $request->getPreferredLanguage($supportedLocales);

        if ($preferredLocale && $preferredLocale !== config('localization.default')) {
            $request->attributes->set('suggested_locale', $preferredLocale);
        }
    }
}
