<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Services\Geocoding\GeocodedLocation;

interface NearestCityLocator
{
    public function nearestCity(float $latitude, float $longitude): ?GeocodedLocation;
}
