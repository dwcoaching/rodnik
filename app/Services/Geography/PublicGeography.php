<?php

declare(strict_types=1);

namespace App\Services\Geography;

use App\Models\City;
use App\Models\Country;
use App\Models\Region;
use App\Models\Report;
use App\Models\Spring;
use Collator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Transliterator;

final class PublicGeography
{
    private ?Transliterator $baseLetters = null;

    /** @return Builder<Spring> */
    public function visibleSprings(): Builder
    {
        return Spring::query()->whereNull('hidden_at')->whereNull('redirect_to_spring_id');
    }

    /** @return Builder<Country> */
    public function countries(): Builder
    {
        return $this->withSourceCounts(Country::query(), 'country_id');
    }

    /** @return Builder<Region> */
    public function regions(?Country $country = null): Builder
    {
        $places = Region::query()
            ->when($country !== null, fn (Builder $query): Builder => $query->where('regions.country_id', $country->id));

        return $this->withSourceCounts($places, 'region_id', $country !== null);
    }

    /** @return Builder<City> */
    public function cities(?Country $country = null, ?Region $region = null): Builder
    {
        $places = City::query()
            ->when($country !== null, fn (Builder $query): Builder => $query->where('cities.country_id', $country->id))
            ->when($region !== null, fn (Builder $query): Builder => $query->where('cities.region_id', $region->id));

        return $this->withSourceCounts($places, 'nearest_city_id', $country !== null || $region !== null);
    }

    /**
     * Settlements a country page lists itself: outside any region, or in a region without a page of its own
     * (one with no sources), which would leave them unreachable from the country.
     *
     * @param  list<int>  $listedRegionIds
     * @return Builder<City>
     */
    public function citiesOutsideRegions(Country $country, array $listedRegionIds = []): Builder
    {
        $places = City::query()->where('cities.country_id', $country->id)
            ->where(fn (Builder $query): Builder => $query->whereNull('cities.region_id')->orWhereNotIn('cities.region_id', $listedRegionIds));

        return $this->withSourceCounts($places, 'nearest_city_id', true);
    }

    /** @return Builder<Country>|Builder<Region>|Builder<City> */
    public function places(string $type): Builder
    {
        return match ($type) {
            'countries' => $this->countries(),
            'regions' => $this->regions(),
            'cities' => $this->cities(),
            default => throw new InvalidArgumentException('Unknown geography type.'),
        };
    }

    /** @return Builder<Spring> */
    public function springsFor(Country|Region|City $place): Builder
    {
        $column = match (true) {
            $place instanceof Country => 'country_id',
            $place instanceof Region => 'region_id',
            $place instanceof City => 'nearest_city_id',
        };

        return $this->visibleSprings()->where($column, $place->id);
    }

    /** @return Collection<int, Country> */
    public function countryDirectory(): Collection
    {
        return $this->remember('countries', $this->listed($this->countries()));
    }

    /** @return Collection<int, Region> */
    public function regionDirectory(Country $country): Collection
    {
        return $this->remember('country.'.$country->id.'.regions', $this->listed($this->regions($country)));
    }

    /**
     * Settlements of a region, or a country's settlements outside its regions.
     *
     * @return Collection<int, City>
     */
    public function settlementDirectory(Country|Region $place): Collection
    {
        return $place instanceof Country
            ? $this->remember('country.'.$place->id.'.unlisted-regions', $this->listed($this->citiesOutsideRegions($place, $this->regionDirectory($place)->modelKeys())))
            : $this->remember('region.'.$place->id.'.settlements', $this->listed($this->cities(region: $place)));
    }

    /** @return Collection<int, City> */
    public function topSettlements(Country $country, int $limit): Collection
    {
        $cities = $this->listed($this->cities($country))
            ->orderByDesc('source_counts.sources_count')->orderBy('cities.id')->limit($limit);

        return $this->remember('country.'.$country->id.'.top-settlements.'.$limit, $cities);
    }

    /** @return Collection<int, Spring> */
    public function mostReported(Country|Region $place, int $limit): Collection
    {
        $type = $place instanceof Country ? 'country' : 'region';
        $reports = Report::query()->visible()->select('reports.spring_id')
            ->selectRaw('COUNT(*) AS reports_count, MAX(reports.id) AS latest_report_id')
            ->groupBy('reports.spring_id');
        // Reports are far fewer than sources, so the grouped reports drive the join.
        $springs = Spring::query()->fromSub($reports, 'report_counts')
            ->join('springs', 'springs.id', '=', 'report_counts.spring_id')
            ->where('springs.'.$type.'_id', $place->id)
            ->whereNull('springs.hidden_at')->whereNull('springs.redirect_to_spring_id')
            ->select(['springs.id', 'springs.name', 'springs.type', 'springs.nearest_city_id', 'report_counts.reports_count'])
            ->orderByDesc('report_counts.reports_count')->orderByDesc('report_counts.latest_report_id')
            ->limit($limit);

        return $this->remember($type.'.'.$place->id.'.most-reported.'.$limit, $springs)
            ->load('nearestCity:id,slug,name,name_ru,is_active');
    }

    /**
     * @template T of Country|Region|City
     *
     * @param  BaseCollection<int, T>  $places
     * @return BaseCollection<int, T>
     */
    public function alphabetical(BaseCollection $places): BaseCollection
    {
        $locale = app()->getLocale();
        $collator = new Collator($locale);

        // ICU sort keys compare bytewise and never contain a zero byte, so the
        // padded ID after one only orders places with the same name.
        return $places->sortBy(fn (Country|Region|City $place): string => ($collator->getSortKey($place->displayName($locale)) ?: $place->displayName($locale))
            ."\0".mb_str_pad((string) $place->id, 20, '0', STR_PAD_LEFT), SORT_STRING)->values();
    }

    /**
     * Splits alphabetically sorted places into runs sharing an initial letter.
     *
     * @template T of Country|Region|City
     *
     * @param  iterable<T>  $places
     * @return list<array{letter: string, places: list<T>}>
     */
    public function byInitial(iterable $places): array
    {
        $locale = app()->getLocale();
        $collator = new Collator($locale);
        $collator->setStrength(Collator::PRIMARY);
        $letters = [];
        $groups = [];

        foreach ($places as $place) {
            $first = mb_strtoupper(mb_substr($place->displayName($locale), 0, 1));
            $letter = $letters[$first] ??= $this->initial($first, $collator);

            if ($groups === [] || $groups[array_key_last($groups)]['letter'] !== $letter) {
                $groups[] = ['letter' => $letter, 'places' => []];
            }
            $groups[array_key_last($groups)]['places'][] = $place;
        }

        return $groups;
    }

    /**
     * Accented forms join their base letter only where the locale's alphabet
     * does too: Ё joins Е and Å joins A, while Й stays separate from И.
     */
    private function initial(string $letter, Collator $collator): string
    {
        if (preg_match('/^\p{L}/u', $letter) !== 1) {
            return '#';
        }

        $this->baseLetters ??= Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC; Latin-ASCII');
        $base = (string) $this->baseLetters?->transliterate($letter);

        return $base !== '' && $base !== $letter && $collator->compare($base, $letter) === 0 ? mb_substr($base, 0, 1) : $letter;
    }

    /**
     * Caches plain rows because the cache does not unserialize objects.
     *
     * @template T of Model
     *
     * @param  Builder<T>  $query
     * @return Collection<int, T>
     */
    private function remember(string $key, Builder $query): Collection
    {
        $rows = Cache::remember('geography.'.$key, (int) config('geography.cache_seconds'), fn (): array => $query->toBase()->get()
            ->map(fn (object $row): array => (array) $row)->all());

        return $query->hydrate($rows);
    }

    /**
     * @template T of Country|Region|City
     *
     * @param  Builder<T>  $places
     * @return Builder<T>
     */
    private function listed(Builder $places): Builder
    {
        $table = $places->getModel()->getTable();

        return $places->select([$table.'.id', $table.'.slug', $table.'.name', $table.'.name_ru', 'source_counts.sources_count']);
    }

    /**
     * @template T of Country|Region|City
     *
     * @param  Builder<T>  $places
     * @return Builder<T>
     */
    private function withSourceCounts(Builder $places, string $column, bool $filterCounts = false): Builder
    {
        // Group the indexed spring links once rather than querying each of the
        // hundreds of thousands of settlements individually.
        $table = $places->getModel()->getTable();
        $counts = $this->visibleSprings();

        if ($filterCounts) {
            // Restrict settlement identities before grouping. The source's own
            // country/region remains independent of its nearest settlement.
            // Starting from the matching places probes only their sources; left
            // alone, MySQL may scan the links of every source in the world.
            $counts->selectRaw('/*+ JOIN_ORDER('.$table.', springs) */ COUNT(*) AS sources_count')
                ->addSelect($table.'.id AS '.$column)
                ->join($table, $table.'.id', '=', 'springs.'.$column)->where($table.'.is_active', true)
                ->mergeWheres($places->getQuery()->wheres, $places->getQuery()->getRawBindings()['where'])
                ->groupBy($table.'.id');
        } else {
            $counts->selectRaw('COUNT(*) AS sources_count')->addSelect('springs.'.$column)
                ->whereNotNull('springs.'.$column)->groupBy('springs.'.$column);
        }

        return $places->select($table.'.*', 'source_counts.sources_count')
            ->joinSub($counts, 'source_counts', $table.'.id', '=', 'source_counts.'.$column)
            ->where($table.'.is_active', true);
    }
}
