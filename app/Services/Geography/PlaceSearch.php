<?php

declare(strict_types=1);

namespace App\Services\Geography;

use App\Models\City;
use App\Models\Country;
use App\Models\Map;
use App\Models\Region;
use App\Models\Spring;
use App\Models\User;
use App\Services\Search\MapArea;
use App\Services\Search\NameMatcher;
use App\Services\Search\SavedMapSearch;
use App\Services\Search\SearchText;
use App\Services\Search\SearchUrlResolver;
use App\Services\Search\SpringSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Normalizer;

final class PlaceSearch
{
    public const LIMIT = 6;

    private const MAX_QUERY_LENGTH = 100;

    // Links are resolved before the query is shortened: a long one would lose its path.
    private const MAX_LINK_LENGTH = 2000;

    // Groups that match equally well keep this order.
    private const GROUPS = ['places', 'springs', 'maps'];

    private const CITY_CANDIDATES = 30;

    private const AREA_CANDIDATES = 20;

    private const SOURCES_CACHE_SECONDS = 3600;

    private const TYPE_ORDER = ['country' => 0, 'region' => 1, 'city' => 2];

    private const ROUTES = ['country' => 'geography.countries.show', 'region' => 'geography.regions.show', 'city' => 'geography.cities.show'];

    private const BOUNDS = ['bbox_west', 'bbox_south', 'bbox_east', 'bbox_north'];

    // Comparable to settlements of about 10 and 1 million people, so "mo" puts Moscow above Monaco.
    private const AREA_IMPORTANCE = ['country' => 7.0, 'region' => 6.0];

    private const VIEW_CANDIDATES = 15;

    // Settlements in the visible map area outrank same-named ones elsewhere, except much larger places.
    private const VIEW_BOOST = ['inside' => 4.5, 'near' => 2.0];

    // An area counts every source inside it, a settlement only those it is nearest to,
    // so area counts get less weight; otherwise "Krasnodar Krai" outranks the exact "Krasnodar".
    private const SOURCES_WEIGHT = ['country' => 0.25, 'region' => 0.25, 'city' => 0.5];

    public function __construct(
        private readonly PublicGeography $geography,
        private readonly SpringSearch $springs,
        private readonly SavedMapSearch $maps,
        private readonly SearchUrlResolver $links,
    ) {}

    /**
     * Places, water sources and saved maps, each kind of result kept together.
     *
     * @param  array{west: float, south: float, east: float, north: float}|null  $view  visible map area; west exceeds east across the antimeridian
     * @return array{query: string, corrected: ?string, results: list<array<string, mixed>>}
     */
    public function search(string $input, ?string $locale = null, ?array $view = null, ?User $user = null): array
    {
        $locale ??= app()->getLocale();
        $text = $this->clean($input);
        $query = mb_trim(mb_substr($text, 0, self::MAX_QUERY_LENGTH));
        $link = $this->links->resolve($text);

        if ($link !== null) {
            // A link names one page: its words are not searched, and it is not retyped on another layout.
            return ['query' => $query, 'corrected' => null, 'results' => $link === false ? [] : [$this->linked($link, $locale)]];
        }

        $results = $this->results($query, $locale, $view, $user);
        $corrected = null;

        if ($results === [] && ($swapped = KeyboardLayout::swap($query)) !== null) {
            $results = $this->results($swapped, $locale, $view, $user);
            $corrected = $results === [] ? null : $swapped;
        }

        return ['query' => $query, 'corrected' => $corrected, 'results' => $results];
    }

    private function clean(string $input): string
    {
        $text = mb_substr(mb_scrub($input, 'UTF-8'), 0, self::MAX_LINK_LENGTH);
        $text = Normalizer::normalize($text, Normalizer::FORM_C) ?: $text;

        return mb_trim(preg_replace(['/[\s\p{Z}]+/u', '/[\p{Cc}\p{Cf}]+/u', '/ {2,}/'], [' ', '', ' '], $text) ?? '');
    }

    /**
     * @param  array{west: float, south: float, east: float, north: float}|null  $view
     * @return list<array<string, mixed>>
     */
    private function results(string $query, string $locale, ?array $view, ?User $user): array
    {
        $numbered = $this->springs->numbered($query);

        // "#123" and "№ 123" ask for a number, while plain digits may also be part of a name.
        if (preg_match('/^[#№]/u', $query) === 1 && ctype_digit(mb_ltrim(mb_substr($query, 1)))) {
            return $numbered === null ? [] : [$this->springs->item($numbered, $locale)];
        }

        $term = preg_replace('/^[\s\p{Pd}]+|[\s\p{Pd}]+$/u', '', $query) ?? '';
        $matcher = new NameMatcher($term);
        $springs = $this->springs->named($term, $matcher, $locale, $view);

        if ($numbered !== null) {
            $springs = [
                ['bonus' => NameMatcher::EXACT, 'item' => $this->springs->item($numbered, $locale)],
                ...array_filter($springs, fn (array $hit): bool => $hit['item']['id'] !== $numbered->id),
            ];
        }

        return $this->arrange([
            'places' => $this->places($term, $matcher, $locale, $view),
            'springs' => array_slice($springs, 0, SpringSearch::LIMIT),
            'maps' => $this->maps->titled($term, $matcher, $locale, $user),
        ]);
    }

    /**
     * The group with the closest match comes first: exact names, then prefixes, then other word matches.
     *
     * @param  array<string, list<array{bonus: float, item: array<string, mixed>}>>  $groups
     * @return list<array<string, mixed>>
     */
    private function arrange(array $groups): array
    {
        $groups = array_filter($groups);
        $best = array_map(fn (array $hits): float => max(array_column($hits, 'bonus')), $groups);
        $order = array_flip(self::GROUPS);
        uksort($groups, fn (string $a, string $b): int => [$best[$b], $order[$a]] <=> [$best[$a], $order[$b]]);

        return array_merge(...array_map(fn (array $hits): array => array_column($hits, 'item'), array_values($groups)));
    }

    /** @return array<string, mixed> */
    private function linked(Map|Spring|Country|Region|City $target, string $locale): array
    {
        if ($target instanceof Map) {
            return $this->maps->item($target, $locale);
        }

        if ($target instanceof Spring) {
            return $this->springs->item($target, $locale);
        }

        $type = match (true) {
            $target instanceof Country => 'country',
            $target instanceof Region => 'region',
            default => 'city',
        };
        $target->load(match ($type) {
            'country' => [],
            'region' => ['country:id,name,name_ru'],
            default => ['region:id,name,name_ru', 'country:id,name,name_ru'],
        });
        $counts = $type === 'city' ? $this->citySourceCounts([$target->id]) : $this->areaSourceCounts($type, [$target->id]);

        return $this->result([
            'type' => $type,
            'place' => $target,
            'name' => SearchText::display($target->displayName($locale)),
            'alt_name' => null,
            'sources' => $counts[$target->id] ?? 0,
        ], $locale);
    }

    /**
     * @param  array{west: float, south: float, east: float, north: float}|null  $view
     * @return list<array{bonus: float, item: array<string, mixed>}>
     */
    private function places(string $term, NameMatcher $matcher, string $locale, ?array $view): array
    {
        if (SearchText::baseLength($term) < 2) {
            return [];
        }

        $area = $view === null ? null : MapArea::fromView($view);
        $pattern = SearchText::likePattern($term);
        $places = [
            'country' => $this->areas(Country::query(), $pattern, $locale)->get(),
            'region' => $this->areas(Region::query()->with('country:id,name,name_ru'), $pattern, $locale)->get(),
            'city' => $this->cities($term, $pattern, $area),
        ];
        $matches = [];

        foreach ($places as $type => $models) {
            $matches[$type] = [];

            foreach ($models as $place) {
                [$bonus, $altName] = $this->match($matcher, $place, $locale);

                if ($bonus !== null) {
                    $matches[$type][$place->id] = [$place, $bonus, $altName];
                }
            }
        }

        $near = $area?->expanded();
        $counts = [
            'country' => $this->areaSourceCounts('country', array_keys($matches['country'])),
            'region' => $this->areaSourceCounts('region', array_keys($matches['region'])),
            'city' => $this->citySourceCounts(array_keys($matches['city'])),
        ];
        $candidates = [];

        foreach ($matches as $type => $found) {
            foreach ($found as [$place, $bonus, $altName]) {
                $sources = $counts[$type][$place->id] ?? 0;
                $importance = self::AREA_IMPORTANCE[$type] ?? log10(max($place->population ?? 1, 1));
                $proximity = match (true) {
                    ! $place instanceof City || $area === null || $near === null => 0.0,
                    $area->contains((float) $place->latitude, (float) $place->longitude) => self::VIEW_BOOST['inside'],
                    $near->contains((float) $place->latitude, (float) $place->longitude) => self::VIEW_BOOST['near'],
                    default => 0.0,
                };
                $candidates[] = [
                    'type' => $type,
                    'place' => $place,
                    'name' => SearchText::display($place->displayName($locale)),
                    'alt_name' => SearchText::display($altName),
                    'sources' => $sources,
                    'bonus' => $bonus,
                    'score' => $importance + self::SOURCES_WEIGHT[$type] * log10($sources + 1) + $bonus + $proximity,
                ];
            }
        }

        usort($candidates, fn (array $a, array $b): int => [$b['score'], self::TYPE_ORDER[$a['type']], $a['name']]
            <=> [$a['score'], self::TYPE_ORDER[$b['type']], $b['name']]);

        return array_map(fn (array $candidate): array => [
            'bonus' => $candidate['bonus'],
            'item' => $this->result($candidate, $locale),
        ], array_slice($candidates, 0, self::LIMIT));
    }

    /**
     * @template T of Country|Region
     *
     * @param  Builder<T>  $query
     * @return Builder<T>
     */
    private function areas(Builder $query, string $pattern, string $locale): Builder
    {
        $prefix = $pattern.'%';
        $columns = ['id', 'name', 'name_ru', 'slug', ...self::BOUNDS];

        return $query->select($query->getModel() instanceof Region ? [...$columns, 'country_id'] : $columns)
            ->where('is_active', true)
            ->whereNotNull(self::BOUNDS)
            ->where(function (Builder $query) use ($prefix): void {
                foreach (['name', 'name_ru'] as $column) {
                    foreach (['', '% ', '%-', '%('] as $wordStart) {
                        $query->orWhere($column, 'like', $wordStart.$prefix);
                    }
                }
            })
            // Without populations, keep the closest and shortest names when a short query matches many areas.
            ->orderByRaw('CASE WHEN name LIKE ? OR name_ru LIKE ? THEN 0 WHEN name LIKE ? OR name_ru LIKE ? THEN 1 ELSE 2 END', [$pattern, $pattern, $prefix, $prefix])
            ->orderByRaw($locale === 'ru' ? 'CHAR_LENGTH(COALESCE(name_ru, name))' : 'CHAR_LENGTH(name)')
            ->orderBy('id')
            ->limit(self::AREA_CANDIDATES);
    }

    /** @return Collection<int, City> */
    private function cities(string $term, string $pattern, ?MapArea $area): Collection
    {
        $columns = ['id', 'country_id', 'region_id', 'name', 'name_ru', 'slug', 'latitude', 'longitude', 'population'];
        // Pick the largest matches from the covering name indexes alone; checking is_active
        // on every prefix match would read thousands of rows for short queries.
        $prefix = fn (string $column): Builder => City::query()->select('id')
            ->where($column, 'like', $pattern.'%')
            ->orderByDesc('population')->limit(self::CITY_CANDIDATES);
        $cities = City::query()->select(array_map(fn (string $column): string => 'cities.'.$column, $columns))
            ->joinSub($prefix('name')->unionAll($prefix('name_ru')), 'matches', 'matches.id', '=', 'cities.id')
            ->where('cities.is_active', true)
            ->get()->unique();
        $against = SearchText::fullTextQuery($term);

        if ($against !== null) {
            // Later words, such as "Novgorod" in "Velikiy Novgorod", are only in the full-text index. Query it even
            // when many names start with the word: 108 start with "Francisco", and San Francisco would be missed.
            $cities = $cities->merge(City::query()->select($columns)
                ->whereFullText(['name', 'name_ru'], $against, ['mode' => 'boolean'])
                ->where('is_active', true)
                ->orderByDesc('population')->limit(self::CITY_CANDIDATES)->get());
        }

        if ($area !== null && SearchText::baseLength($term) >= 3) {
            // Small villages share names across the country ("Aleksandrovka"); fetch the visible ones too.
            $cities = $cities->merge($area->constrain(City::query()->select($columns)
                ->where('is_active', true)
                ->where(fn (Builder $query): Builder => $query->where('name', 'like', $pattern.'%')->orWhere('name_ru', 'like', $pattern.'%')))
                ->orderByDesc('population')->limit(self::VIEW_CANDIDATES)->get());
        }

        return $cities->load(['region:id,name,name_ru', 'country:id,name,name_ru']);
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, int>
     */
    private function citySourceCounts(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return $this->geography->visibleSprings()->toBase()
            ->selectRaw('nearest_city_id, COUNT(*) AS sources_count')
            ->whereIn('nearest_city_id', $ids)->groupBy('nearest_city_id')
            ->pluck('sources_count', 'nearest_city_id')->map(fn (mixed $count): int => (int) $count)->all();
    }

    /**
     * Country totals reach hundreds of thousands of sources, so each area count is cached on its own.
     *
     * @param  array<int, int>  $ids
     * @return array<int, int>
     */
    private function areaSourceCounts(string $type, array $ids): array
    {
        $keys = array_combine($ids, array_map(fn (int $id): string => "place-search:sources:{$type}:{$id}", $ids));
        $cached = $keys === [] ? [] : Cache::many(array_values($keys));
        $counts = [];
        $missing = [];

        foreach ($keys as $id => $key) {
            if (isset($cached[$key])) {
                $counts[$id] = (int) $cached[$key];
            } else {
                $missing[] = $id;
            }
        }

        if ($missing !== []) {
            $column = $type.'_id';
            $fresh = $this->geography->visibleSprings()->toBase()
                ->selectRaw($column.' AS place_id, COUNT(*) AS sources_count')
                ->whereIn($column, $missing)->groupBy($column)
                ->pluck('sources_count', 'place_id');
            $values = [];

            foreach ($missing as $id) {
                $counts[$id] = (int) ($fresh[$id] ?? 0);
                $values[$keys[$id]] = $counts[$id];
            }

            Cache::putMany($values, self::SOURCES_CACHE_SECONDS);
        }

        return $counts;
    }

    /**
     * The ranking bonus, or null when neither name matches the query; and the other name when only it matches.
     *
     * @return array{?float, ?string}
     */
    private function match(NameMatcher $matcher, Country|Region|City $place, string $locale): array
    {
        $name = $place->displayName($locale);
        $other = $name === $place->name ? $place->name_ru : $place->name;
        $nameBonus = $matcher->bonus($name);
        $otherBonus = $other ? $matcher->bonus($other) : null;

        if ($nameBonus === null && $otherBonus === null) {
            return [null, null];
        }

        return [max($nameBonus ?? 0.0, $otherBonus ?? 0.0), $nameBonus === null && $other !== $name ? $other : null];
    }

    /**
     * @param  array{place: Country|Region|City, type: string, name: ?string, alt_name: ?string, sources: int}  $candidate
     * @return array<string, mixed>
     */
    private function result(array $candidate, string $locale): array
    {
        $place = $candidate['place'];
        $sources = $candidate['sources'];
        $bounds = $place instanceof City ? null : array_map(floatval(...), array_values($place->only(self::BOUNDS)));

        return [
            'type' => $candidate['type'],
            'group' => 'places',
            'id' => $place->id,
            'name' => $candidate['name'],
            'alt_name' => $candidate['alt_name'],
            'context' => match (true) {
                $place instanceof City => SearchText::join([$this->placeName($place->region, $locale), $this->placeName($place->country, $locale)]),
                $place instanceof Region => $this->placeName($place->country, $locale),
                default => null,
            },
            'sources_count' => $sources,
            'sources_label' => $sources > 0
                ? trans_choice('ui.home.water_sources_count', $sources, ['count' => number_format($sources, 0, ',', ' ')], $locale)
                : null,
            'url' => $sources > 0 ? route(localized_route_name(self::ROUTES[$candidate['type']], $locale), $place) : null,
            'center' => $bounds === null ? [(float) $place->longitude, (float) $place->latitude] : $this->center($bounds),
            'bounds' => $bounds,
            'zoom' => $place instanceof City ? match (true) {
                ($place->population ?? 0) >= 1_000_000 => 11,
                ($place->population ?? 0) >= 100_000 => 12,
                default => 13,
            } : null,
        ];
    }

    /**
     * @param  array{float, float, float, float}  $bounds
     * @return array{float, float}
     */
    private function center(array $bounds): array
    {
        [$west, $south, $east, $north] = $bounds;
        $longitude = ($west + $east) / 2;

        return [$longitude >= 180 ? $longitude - 360 : $longitude, ($south + $north) / 2];
    }

    private function placeName(?Model $place, string $locale): ?string
    {
        return $place instanceof Country || $place instanceof Region || $place instanceof City ? SearchText::display($place->displayName($locale)) : null;
    }
}
