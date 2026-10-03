<?php

declare(strict_types=1);

namespace App\Services\Geocoding;

use App\Contracts\GeographyCatalog;
use App\Contracts\ReverseGeocoder;
use App\Contracts\SnapshotReverseGeocoder;

/** Enrich the final geographic policy with stable catalog identities. */
final class GeographyReverseGeocoder implements SnapshotReverseGeocoder
{
    public function __construct(
        private readonly ReverseGeocoder $base,
        private readonly RegionBoundaryIndex $regions,
        private readonly GeographyCatalog $catalog,
    ) {}

    public function validateSource(): void
    {
        $this->base->validateSource();
        $this->regions->validateSource();
    }

    public function reverse(float $latitude, float $longitude): ?GeocodedLocation
    {
        $location = $this->base->reverse($latitude, $longitude);

        if ($location === null) {
            return null;
        }

        $country = $this->catalog->country($location->countryCode);
        $city = $this->catalog->city($location->geonamesId);
        $regionLocation = $location->countryCode === null ? null : $this->regions->region($latitude, $longitude, $location->countryCode);
        $region = $regionLocation === null ? null : $this->catalog->region($regionLocation, isset($country['id']) ? (int) $country['id'] : null);
        $isRussia = $location->countryCode === 'RU';

        return new GeocodedLocation(
            $isRussia ? 'Russia' : ($country['name'] ?? $location->country),
            $location->countryCode,
            $city['name'] ?? $location->city,
            $isRussia ? 'Россия' : ($country['name_ru'] ?? $location->countryRu),
            $region['name'] ?? $regionLocation?->region,
            $location->source,
            countryId: isset($country['id']) ? (int) $country['id'] : null,
            regionId: isset($region['id']) ? (int) $region['id'] : null,
            nearestCityId: isset($city['id']) ? (int) $city['id'] : null,
            cityRu: $city['name_ru'] ?? $location->cityRu,
            regionRu: $region['name_ru'] ?? $regionLocation?->regionRu,
            nearestCityDistanceKm: $location->nearestCityDistanceKm,
            geonamesId: $location->geonamesId,
            cityLatitude: $location->cityLatitude,
            cityLongitude: $location->cityLongitude,
            cityAdmin1Code: $location->cityAdmin1Code,
            regionGeonamesId: $regionLocation?->regionGeonamesId,
            regionIsoCode: $regionLocation?->regionIsoCode,
            regionSourceKey: $regionLocation?->regionSourceKey,
        );
    }

    public function withSnapshot(callable $callback): mixed
    {
        // A new bounded chunk sees catalog updates rather than retaining
        // stale names or negative identity results in a queue worker.
        $this->catalog->clear();
        $run = fn (): mixed => $this->regions->withSnapshot($callback);

        if ($this->base instanceof SnapshotReverseGeocoder) {
            return $this->base->withSnapshot($run);
        }

        $this->base->validateSource();

        return $run();
    }
}
