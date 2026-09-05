<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route as RouteFacade;
use InvalidArgumentException;

final class LocalizedUrl
{
    public function absolute(Request $request, string $locale, bool $canonical = false): string
    {
        return url($this->relative($request, $locale, $canonical));
    }

    public function relative(Request $request, string $locale, bool $canonical = false): string
    {
        $this->ensureSupported($locale);

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

        if ($query === []) {
            return $path;
        }

        return $path.'?'.urldecode(http_build_query($query, '', '&', PHP_QUERY_RFC3986));
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
            'docs/about',
            'docs/legend',
            'docs/contact-us',
            'docs/exports',
            'docs/coll-de-sa-batalla',
            'ru/docs/about',
            'ru/docs/legend',
            'ru/docs/contact-us',
            'ru/docs/exports',
            'ru/docs/coll-de-sa-batalla',
        );
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
