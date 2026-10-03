<?php

declare(strict_types=1);

namespace App\Services\Geocoding;

use App\Contracts\GeographyCatalog;
use Illuminate\Support\Facades\DB;

/** Indexed identity lookups; no worldwide city table is loaded into memory. */
final class DatabaseGeographyCatalog implements GeographyCatalog
{
    private array $countries = [];

    private array $cities = [];

    private array $regions = [];

    public function country(?string $code): ?array
    {
        if ($code === null) {
            return null;
        }

        if (! array_key_exists($code, $this->countries)) {
            $record = DB::table('countries')->where('code', $code)->where('is_active', true)->first(['id', 'name', 'name_ru']);
            $this->countries[$code] = $record === null ? null : (array) $record;
        }

        return $this->countries[$code];
    }

    public function city(?int $geonamesId): ?array
    {
        if ($geonamesId === null) {
            return null;
        }

        if (! array_key_exists($geonamesId, $this->cities)) {
            if (count($this->cities) >= 1024) {
                $this->cities = [];
            }

            $record = DB::table('cities')->where('geonames_id', $geonamesId)->where('is_active', true)->first(['id', 'name', 'name_ru', 'latitude', 'longitude']);
            $this->cities[$geonamesId] = $record === null ? null : (array) $record;
        }

        return $this->cities[$geonamesId];
    }

    public function region(GeocodedLocation $location, ?int $countryId): ?array
    {
        if ($countryId === null || ($location->regionSourceKey === null && $location->regionGeonamesId === null && $location->regionIsoCode === null)) {
            return null;
        }

        $key = $countryId.':'.($location->regionSourceKey ?? $location->regionGeonamesId ?? $location->regionIsoCode);

        if (! array_key_exists($key, $this->regions)) {
            $query = DB::table('regions')->where('country_id', $countryId)->where('is_active', true);

            if ($location->regionGeonamesId !== null) {
                $query->where('geonames_id', $location->regionGeonamesId);
            } elseif ($location->regionSourceKey !== null) {
                $query->where('source_key', $location->regionSourceKey);
            } else {
                $query->where('iso_code', $location->regionIsoCode);
            }

            $record = $query->first(['id', 'name', 'name_ru']);
            $this->regions[$key] = $record === null ? null : (array) $record;
        }

        return $this->regions[$key];
    }

    public function clear(): void
    {
        $this->countries = $this->cities = $this->regions = [];
    }
}
