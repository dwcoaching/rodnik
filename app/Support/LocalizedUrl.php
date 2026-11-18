<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route as RouteFacade;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class LocalizedUrl
{
    public function absolute(Request $request, string $locale, bool $canonical = false): string
    {
        return mb_rtrim(url('/'), '/').$this->relative($request, $locale, $canonical);
    }

    public function relative(Request $request, string $locale, bool $canonical = false): string
    {
        $this->ensureSupported($locale);

        if ($request->attributes->has('duo.page')) {
            return app(DuoUrl::class)->relative(
                $request->attributes->get('duo.page'),
                $locale,
                $request->query(),
                $canonical,
            );
        }

        $path = '/'.mb_ltrim($request->path(), '/');
        $path = $path === '/' ? $path : mb_rtrim($path, '/');

        if ($this->hasLocalizedEquivalent($request)) {
            $path = $this->withoutLocalePrefix($path);

            if ($locale !== config('localization.default')) {
                $path = '/'.$locale.($path === '/' ? '' : $path);
            }
        }

        $query = $canonical
            ? Arr::where(
                $request->query(),
                fn (mixed $value, string $key): bool => ! str_starts_with($key, 'utm_')
                    && ! in_array($key, ['gclid', 'redirect'], true),
            )
            : $request->query();

        $pageParameters = match (true) {
            $request->routeIs('geography.*', 'ru.geography.*') => ['page', 'settlements_page'],
            $request->is('docs/maps', 'ru/docs/maps') => ['page'],
            default => null,
        };

        if ($canonical && $pageParameters !== null) {
            $query = [];

            foreach ($pageParameters as $name) {
                $page = $request->query($name);
                if (is_scalar($page) && filter_var($page, FILTER_VALIDATE_INT, ['options' => ['min_range' => 2]]) !== false) {
                    $query[$name] = (int) $page;
                }
            }
        }

        if ($query === []) {
            return $path;
        }

        return $path.'?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * The locale a local path is published in, or null when the path has no localized versions.
     */
    public function localeOfPath(string $path): ?string
    {
        $path = $this->pathOnly($path);

        if (! $this->pathHasLocalizedEquivalent($path)) {
            return null;
        }

        foreach (array_keys(config('localization.supported')) as $locale) {
            if ($locale !== config('localization.default') && ($path === '/'.$locale || str_starts_with($path, '/'.$locale.'/'))) {
                return $locale;
            }
        }

        return config('localization.default');
    }

    /**
     * Rewrite a local URL (path, query and fragment) into the given locale. Paths without a
     * localized version, such as /login, come back unchanged.
     */
    public function forPath(string $url, string $locale): string
    {
        $this->ensureSupported($locale);

        $path = $this->pathOnly($url);

        if (! $this->pathHasLocalizedEquivalent($path)) {
            return $url;
        }

        $localized = $this->withoutLocalePrefix($path);

        if ($locale !== config('localization.default')) {
            $localized = '/'.$locale.($localized === '/' ? '' : $localized);
        }

        return $localized.mb_substr($url, mb_strlen($path));
    }

    public function hasLocalizedEquivalent(Request $request): bool
    {
        $route = $request->route();

        if ($route instanceof Route && $route->getName()) {
            $routeName = str_starts_with($route->getName(), 'ru.')
                ? mb_substr($route->getName(), 3)
                : $route->getName();

            if (RouteFacade::has($routeName) && RouteFacade::has('ru.'.$routeName)) {
                return true;
            }
        }

        return $request->is('docs')
            || $request->is('docs/*')
            || $request->is('ru/docs')
            || $request->is('ru/docs/*');
    }

    public function isIndexable(Request $request): bool
    {
        if (! $request->isMethodSafe()) {
            return false;
        }

        if ($request->attributes->has('duo.page')) {
            return ! $request->attributes->get('duo.page')['location']
                && ! $request->attributes->get('duo.spring')?->hidden_at
                && $request->query('redirect') !== 'false';
        }

        $route = $request->route();

        if ($route instanceof Route && $route->getName()) {
            $routeName = str_starts_with($route->getName(), 'ru.')
                ? mb_substr($route->getName(), 3)
                : $route->getName();

            if ($routeName === 'duo') {
                return ! $request->has('page.location')
                    && $request->query('redirect') !== 'false';
            }

            if ($routeName === 'springs.history') {
                return true;
            }
        }

        return $request->is(
            'docs',
            'docs/maps',
            'docs/about',
            'docs/legend',
            'docs/contact-us',
            'docs/exports',
            'docs/privacy',
            'docs/delete-account',
            'docs/coll-de-sa-batalla',
            'ru/docs',
            'ru/docs/maps',
            'ru/docs/about',
            'ru/docs/legend',
            'ru/docs/contact-us',
            'ru/docs/exports',
            'ru/docs/privacy',
            'ru/docs/delete-account',
            'ru/docs/coll-de-sa-batalla',
        );
    }

    private function pathOnly(string $url): string
    {
        $path = strpbrk($url, '?#');

        return $path === false ? $url : mb_substr($url, 0, mb_strlen($url) - mb_strlen($path));
    }

    private function pathHasLocalizedEquivalent(string $path): bool
    {
        $request = Request::create($path);

        try {
            $route = RouteFacade::getRoutes()->match($request);
            $request->setRouteResolver(fn (): Route => $route);
        } catch (HttpException) {
            // Folio docs pages are recognised by path below.
        }

        return $this->hasLocalizedEquivalent($request);
    }

    private function withoutLocalePrefix(string $path): string
    {
        foreach (array_keys(config('localization.supported')) as $locale) {
            if ($locale === config('localization.default')) {
                continue;
            }

            if ($path === '/'.$locale) {
                return '/';
            }

            if (str_starts_with($path, '/'.$locale.'/')) {
                return mb_substr($path, mb_strlen($locale) + 1);
            }
        }

        return $path;
    }

    private function ensureSupported(string $locale): void
    {
        if (! array_key_exists($locale, config('localization.supported'))) {
            throw new InvalidArgumentException("Unsupported locale [{$locale}].");
        }
    }
}
