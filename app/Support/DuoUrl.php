<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Spring;
use App\Models\User;
use Illuminate\Contracts\Routing\UrlRoutable;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

final class DuoUrl
{
    /**
     * @return array{page: array{spring: ?int, user: ?int, location: ?int}, spring: ?Spring, user: ?User, coordinates: array<int, float>}
     */
    public function resolve(Request $request): array
    {
        $page = $this->page($request);
        abort_if(($request->route('springId') ?? $request->route('spring')) !== null && $page['spring'] === null, 404);
        abort_if($request->route('userId') !== null && $page['user'] === null, 404);

        $spring = $page['spring'] === null ? null : Spring::query()->findOrFail($page['spring']);
        $user = $page['user'] === null ? null : User::query()->findOrFail($page['user']);

        if ($spring !== null && $request->query('redirect') !== 'false') {
            $spring = $spring->finallyRedirectedTo() ?? $spring;
            $page['spring'] = $spring->id;
        }

        $request->attributes->set('duo.page', $page);
        $request->attributes->set('duo.spring', $spring);
        $request->attributes->set('duo.user', $user);

        $coordinates = $spring === null ? [] : [(float) $spring->longitude, (float) $spring->latitude];

        return compact('page', 'spring', 'user', 'coordinates');
    }

    /**
     * @return array{spring: ?int, user: ?int, location: ?int}
     */
    public function page(Request $request): array
    {
        $legacy = $request->query('page', $request->query('view', []));
        $legacy = is_array($legacy) ? $legacy : [];

        return [
            'spring' => $this->identifier($request->route('springId') ?? $request->route('spring') ?? $request->query('spring', $legacy['spring'] ?? $request->query('s', $request->query('spring_id')))),
            'user' => $this->identifier($request->route('userId') ?? $request->query('user', $legacy['user'] ?? $request->query('u'))),
            'location' => ($request->route('location') ?? $request->query('location', $legacy['location'] ?? $request->query('locating'))) ? 1 : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $page
     * @param  array<string, mixed>  $query
     */
    public function relative(array $page = [], ?string $locale = null, array $query = [], bool $canonical = false): string
    {
        $locale ??= app()->getLocale();
        $spring = $this->identifier($page['spring'] ?? null);
        $user = $this->identifier($page['user'] ?? null);
        $path = $spring ? '/'.$spring.'/' : ($user ? '/users/'.$user.'/' : '/');
        $path = localized_public_path($path, $locale);

        if ($canonical) {
            return $path;
        }

        $query = array_merge(
            Arr::except($query, ['page', 'view', 's', 'u', 'spring', 'spring_id', 'user', 'location', 'locating']),
            Arr::except($page, ['spring', 'user', 'location']),
        );

        if ($spring && $user) {
            $query['user'] = $user;
        }

        if ($page['location'] ?? null) {
            $query['location'] = 1;
        }

        return $path.($query === [] ? '' : '?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986));
    }

    /**
     * @param  array<string, mixed>  $page
     * @param  array<string, mixed>  $query
     */
    public function absolute(array $page = [], ?string $locale = null, array $query = [], bool $canonical = false): string
    {
        return mb_rtrim(url('/'), '/').$this->relative($page, $locale, $query, $canonical);
    }

    public function identifier(mixed $value): ?int
    {
        if ($value instanceof UrlRoutable) {
            $value = $value->getRouteKey();
        }

        if ($value === null || $value === '' || $value === 0 || $value === '0') {
            return null;
        }

        abort_unless((is_int($value) || is_string($value)) && ctype_digit((string) $value), 404);
        $id = filter_var(mb_ltrim((string) $value, '0'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        abort_if($id === false, 404);

        return $id;
    }
}
