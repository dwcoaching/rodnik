<?php

declare(strict_types=1);

namespace App\Support\Docs;

use App\Library\Export\FileParser;
use App\Library\StatisticsService;
use App\Library\UserRanking;
use App\Models\City;
use App\Models\Country;
use App\Models\Region;
use App\Models\User;
use App\Services\Geography\PublicGeography;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

final class DocsOverview
{
    public const string CACHE_KEY = 'docs.overview';

    public const int CACHE_SECONDS = 600;

    // After CACHE_SECONDS visitors still get the previous numbers while a deferred refresh runs.
    public const int STALE_SECONDS = 86400;

    /** @var array<string, array{string, string}> */
    private const array PLACE_ROUTES = [
        'countries' => ['geography.countries.show', 'country'],
        'regions' => ['geography.regions.show', 'region'],
        'settlements' => ['geography.cities.show', 'city'],
    ];

    public function __construct(
        private readonly PublicGeography $geography,
        private readonly UserRanking $ranking,
        private readonly PublicMaps $maps,
    ) {}

    /** @return array<string, mixed> */
    public function data(): array
    {
        $aggregates = Cache::flexible(self::CACHE_KEY, [self::CACHE_SECONDS, self::STALE_SECONDS], fn (): array => $this->aggregates());
        $places = [];

        foreach ($aggregates['places'] as $type => $list) {
            $places[$type] = array_map(fn (array $place): array => $this->localizedPlace($type, $place), $list);
        }

        return [
            'sourcesCount' => (int) StatisticsService::getSpringsCount(),
            'reportsCount' => (int) StatisticsService::getReportsCount(),
            'contributorsCount' => $aggregates['contributorsCount'],
            'contributors' => $aggregates['contributors'],
            'countriesCount' => $aggregates['countriesCount'],
            'places' => $places,
            'mapsCount' => $this->maps->count(),
            'maps' => $this->maps->top(12),
            'exports' => FileParser::getExportFiles(),
        ];
    }

    /**
     * Grouping a million sources by place is too slow to repeat on every
     * visit. The cache store only accepts plain values, not models.
     *
     * @return array<string, mixed>
     */
    private function aggregates(): array
    {
        return [
            'contributorsCount' => $this->ranking->count(),
            'contributors' => $this->ranking->top(10)->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'reports_count' => (int) $user->reports_count,
                'springs_count' => (int) $user->springs_count,
            ])->all(),
            'countriesCount' => $this->geography->countries()->toBase()->getCountForPagination(),
            'places' => [
                'countries' => $this->places($this->geography->countries()
                    ->orderByDesc('sources_count')->orderBy('countries.name')),
                'regions' => $this->places($this->geography->regions()->with('country:id,name,name_ru')
                    ->orderByDesc('sources_count')->orderBy('regions.name')),
                'settlements' => $this->places($this->geography->cities()->with('country:id,name,name_ru')
                    ->orderByDesc('sources_count')->orderBy('cities.name')),
            ],
        ];
    }

    /**
     * @param  Builder<Country>|Builder<Region>|Builder<City>  $query
     * @return list<array{slug: string, names: array<string, string>, country: ?array<string, string>, sources_count: int}>
     */
    private function places(Builder $query): array
    {
        return $query->limit(12)->get()->map(fn (Country|Region|City $place): array => [
            'slug' => $place->slug,
            'names' => $this->names($place),
            'country' => $place instanceof Country || $place->country === null ? null : $this->names($place->country),
            'sources_count' => (int) $place->sources_count,
        ])->values()->all();
    }

    /** @return array<string, string> */
    private function names(Country|Region|City $place): array
    {
        $names = [];

        foreach (array_keys(config('localization.supported')) as $locale) {
            $names[$locale] = $place->displayName($locale);
        }

        return $names;
    }

    /**
     * @param  array{slug: string, names: array<string, string>, country: ?array<string, string>, sources_count: int}  $place
     * @return array{url: string, name: string, country: ?string, sources_count: int}
     */
    private function localizedPlace(string $type, array $place): array
    {
        [$route, $parameter] = self::PLACE_ROUTES[$type];
        $locale = app()->getLocale();
        $default = config('localization.default');

        return [
            'url' => localized_route($route, [$parameter => $place['slug']]),
            'name' => $place['names'][$locale] ?? $place['names'][$default],
            'country' => $place['country'] === null ? null : ($place['country'][$locale] ?? $place['country'][$default]),
            'sources_count' => $place['sources_count'],
        ];
    }
}
