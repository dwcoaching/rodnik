<?php

declare(strict_types=1);

function decline_number($value, $strings, $withNumber = true)
{
    if ($withNumber) {
        $result = $value.' ';
    } else {
        $result = '';
    }

    if ($value > 100) {
        $value = $value % 100;
    }

    $firstDigit = $value % 10;
    $secondDigit = floor($value / 10);

    if ($secondDigit !== 1) {
        if ($firstDigit === 1) {
            $result .= $strings[0];
        } elseif ($firstDigit > 1 && $firstDigit < 5) {
            $result .= $strings[1];
        } else {
            $result .= $strings[2];
        }
    } else {
        $result .= $strings[2];
    }

    return $result;
}

if (! function_exists('mb_ucfirst')) {
    function mb_ucfirst($string, $encoding = 'UTF-8')
    {
        $firstChar = mb_substr($string, 0, 1, $encoding);
        $then = mb_substr($string, 1, null, $encoding);

        return mb_strtoupper($firstChar, $encoding).$then;
    }
}

function without_http($string)
{
    return preg_replace('/https?:\/\/(www\.)?/', '', $string);
}

/** @param array<string, mixed> $parameters */
function duo_route(array $parameters = []): string
{
    return app(App\Support\DuoUrl::class)->absolute($parameters);
}

function localized_url(string $locale, bool $canonical = false): string
{
    return app(App\Support\LocalizedUrl::class)->absolute(request(), $locale, $canonical);
}

function localized_path(string $locale, bool $canonical = false): string
{
    return app(App\Support\LocalizedUrl::class)->relative(request(), $locale, $canonical);
}

function localized_route_name(string $name, ?string $locale = null): string
{
    $locale ??= app()->getLocale();
    $localizedName = $locale === config('localization.default') ? $name : $locale.'.'.$name;

    return Illuminate\Support\Facades\Route::has($localizedName) ? $localizedName : $name;
}

function localized_route(string $name, mixed $parameters = [], bool $absolute = true): string
{
    if (in_array($name, ['springs.show', 'users.show'], true)) {
        $key = $name === 'springs.show' ? 'spring' : 'user';
        $routeKey = $key.'Id';
        $parameters = is_array($parameters) ? $parameters : [$routeKey => $parameters];
        $parameters[$key] = $parameters[$routeKey] ?? $parameters[$key] ?? $parameters[0] ?? null;
        unset($parameters[$routeKey], $parameters[0]);

        $duoUrl = app(App\Support\DuoUrl::class);

        return $absolute ? $duoUrl->absolute($parameters) : $duoUrl->relative($parameters);
    }

    return route(localized_route_name($name), $parameters, $absolute);
}

function localized_public_path(string $path = '/', ?string $locale = null): string
{
    $locale ??= app()->getLocale();
    $path = '/'.mb_ltrim($path, '/');

    if ($locale === config('localization.default')) {
        return $path;
    }

    return '/'.$locale.($path === '/' ? '' : $path);
}
