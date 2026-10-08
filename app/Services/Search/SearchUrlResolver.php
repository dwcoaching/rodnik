<?php

declare(strict_types=1);

namespace App\Services\Search;

use App\Models\City;
use App\Models\Country;
use App\Models\Map;
use App\Models\Region;
use App\Models\Spring;

/**
 * Finds what a pasted link to one of the site's pages shows, on any host: saved maps, water sources and place pages.
 */
final class SearchUrlResolver
{
    private const BOUNDS = ['bbox_west', 'bbox_south', 'bbox_east', 'bbox_north'];

    public function __construct(
        private readonly SpringSearch $springs,
        private readonly SavedMapSearch $maps,
    ) {}

    /** Null when the query is not a link, false when it links to nothing that can be found. */
    public function resolve(string $query): Map|Spring|Country|Region|City|false|null
    {
        $link = $this->link($query);

        if ($link === null) {
            return null;
        }

        $path = preg_replace('~^/(?:ru|en)(?=/|$)~', '', $link['path']) ?: '/';

        // Older links kept the water source in the query string ("/?s=141635"); the site still redirects them.
        if ($path === '/' && ($id = $this->legacySpring($link['query'])) !== null) {
            return $this->springs->find($id) ?? false;
        }

        return match (true) {
            preg_match('~^/maps/([^/]+)(?:/login)?/?$~', $path, $matches) === 1 => $this->maps->find($matches[1]),
            preg_match('~^/(?:springs/)?([0-9]{1,10})(?:/(?:edit|history|location/edit))?/?$~', $path, $matches) === 1 => $this->springs->find((int) $matches[1]),
            preg_match('~^/(?:docs/)?(countries|regions|cities)/([a-zA-Z0-9-]{1,255})/?$~', $path, $matches) === 1 => $this->place($matches[1], $matches[2]),
            default => null,
        } ?? false;
    }

    /** @return array{path: string, query: string}|null */
    private function link(string $query): ?array
    {
        if (preg_match('/\s/u', $query) === 1) {
            return null;
        }

        // Links copied from a sentence may end with its punctuation.
        $query = mb_rtrim($query, '.,;:!?)');
        // "https://host/path" and "//host/path", "host.tld/path" or "localhost:8001/path", and "/path".
        $patterns = ['~^(?:[a-z][a-z0-9+.-]*:)?//[^/?#]*([^?#]*)(?:\?([^#]*))?~i', '~^(?:(?:[\p{L}\p{N}-]+\.)+\p{L}[\p{L}\p{N}-]*|localhost)(?::[0-9]+)?(/[^?#]*)(?:\?([^#]*))?~iu', '~^(/[^?#]*)(?:\?([^#]*))?~'];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $query, $matches) === 1) {
                return ['path' => $matches[1] ?: '/', 'query' => $matches[2] ?? ''];
            }
        }

        return null;
    }

    // The same parameters App\Support\DuoUrl::page() reads: spring, page[spring] or view[spring], s, spring_id.
    private function legacySpring(string $query): ?int
    {
        parse_str($query, $parameters);
        $legacy = $parameters['page'] ?? $parameters['view'] ?? [];
        $id = $parameters['spring'] ?? (is_array($legacy) ? $legacy['spring'] ?? null : null) ?? $parameters['s'] ?? $parameters['spring_id'] ?? null;

        return is_string($id) && preg_match('/^[0-9]{1,10}$/', $id) === 1 ? (int) $id : null;
    }

    private function place(string $type, string $slug): Country|Region|City|null
    {
        return match ($type) {
            'countries' => Country::query()->where('slug', $slug)->where('is_active', true)->whereNotNull(self::BOUNDS)->first(),
            'regions' => Region::query()->where('slug', $slug)->where('is_active', true)->whereNotNull(self::BOUNDS)->first(),
            default => City::query()->where('slug', $slug)->where('is_active', true)->first(),
        };
    }
}
