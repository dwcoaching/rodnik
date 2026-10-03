<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Services\Geocoding\GeocodedLocation;

interface GeographyCatalog
{
    public function country(?string $code): ?array;

    public function city(?int $geonamesId): ?array;

    public function region(GeocodedLocation $location, ?int $countryId): ?array;

    public function clear(): void;
}
