<?php

declare(strict_types=1);

namespace App\Services\Geography;

use App\Models\City;
use App\Models\Country;
use App\Models\Region;
use App\Models\Spring;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

final class PublicGeography
{
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
        $counts = $this->visibleSprings()->select($column)
            ->selectRaw('COUNT(*) AS sources_count')->whereNotNull($column)->groupBy($column);
        $table = $places->getModel()->getTable();

        if ($filterCounts) {
            // Restrict settlement identities before grouping. The source's own
            // country/region remains independent of its nearest settlement.
            $ids = (clone $places)->select($table.'.id')->where($table.'.is_active', true);
            $counts->whereIn($column, $ids);
        }

        return $places->select($table.'.*', 'source_counts.sources_count')
            ->joinSub($counts, 'source_counts', $table.'.id', '=', 'source_counts.'.$column)
            ->where($table.'.is_active', true);
    }
}
