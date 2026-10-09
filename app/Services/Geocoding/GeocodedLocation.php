<?php

declare(strict_types=1);

namespace App\Services\Geocoding;

final readonly class GeocodedLocation
{
    public function __construct(
        public ?string $country,
        public ?string $countryCode,
        public ?string $city,
        public ?string $countryRu = null,
        public ?string $region = null,
        public string $source = 'geojson',
        public ?int $countryId = null,
        public ?int $regionId = null,
        public ?int $nearestCityId = null,
        public ?string $cityRu = null,
        public ?string $regionRu = null,
        public ?float $nearestCityDistanceKm = null,
        public ?int $geonamesId = null,
        public ?float $cityLatitude = null,
        public ?float $cityLongitude = null,
        public ?string $cityAdmin1Code = null,
        public ?int $regionGeonamesId = null,
        public ?string $regionIsoCode = null,
        public ?string $regionSourceKey = null,
    ) {}

    /** Water sources store only catalog links; names are read from the catalog. */
    public function attributes(): array
    {
        return [
            'geocoding_source' => $this->source,
            'country_id' => $this->countryId,
            'region_id' => $this->regionId,
            'nearest_city_id' => $this->nearestCityId,
            'nearest_city_distance_km' => $this->nearestCityDistanceKm,
        ];
    }
}
