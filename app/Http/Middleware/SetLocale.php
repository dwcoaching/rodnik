<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\LocalizedUrl;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

final class SetLocale
{
    public function __construct(private readonly LocalizedUrl $localizedUrl) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, ?string $forcedLocale = null): Response
    {
        $locale = $forcedLocale ?? $this->resolveLocale($request);

        abort_unless(array_key_exists($locale, config('localization.supported')), 404);

        if ($redirect = $this->redirectToChosenLocale($request, $locale)) {
            return $redirect;
        }

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
            return $this->publicUrlLocale($request, $route);
        }

        if ($chosenLocale = $this->chosenLocale($request)) {
            return $chosenLocale;
        }

        $headerLocale = $request->header('X-Rodnik-Locale');

        if (is_string($headerLocale) && in_array($headerLocale, $supportedLocales, true)) {
            return $headerLocale;
        }

        return $request->getPreferredLanguage($supportedLocales)
            ?? config('localization.default');
    }

    /**
     * The language the visitor picked with the switcher: the account's, else the cookie's.
     * Null when they never picked one — the browser's language is a guess, not a choice.
     */
    private function chosenLocale(Request $request): ?string
    {
        $supportedLocales = array_keys(config('localization.supported'));

        foreach ([$request->user()?->locale, $request->cookie(config('localization.cookie'))] as $locale) {
            if (is_string($locale) && in_array($locale, $supportedLocales, true)) {
                return $locale;
            }
        }

        return null;
    }

    /**
     * A page address names its language, but a visitor who picked a language keeps it: a link
     * to the other language's version of a page leads them to the version in theirs. Requests
     * made by page scripts are left alone, as are pages published in one language only.
     */
    private function redirectToChosenLocale(Request $request, string $locale): ?RedirectResponse
    {
        if (! $request->isMethod('GET') || $request->expectsJson() || $request->hasHeader('X-Rodnik-Locale')) {
            return null;
        }

        $chosenLocale = $this->chosenLocale($request);

        if ($chosenLocale === null || $chosenLocale === $locale) {
            return null;
        }

        $url = $request->getRequestUri();
        $chosenUrl = $this->localizedUrl->forPath($url, $chosenLocale);

        return $chosenUrl === $url ? null : redirect($chosenUrl);
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
}
