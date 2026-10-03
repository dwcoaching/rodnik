<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Services\Geocoding\GeocodedLocation;

interface ReverseGeocoder
{
    public function validateSource(): void;

    public function reverse(float $latitude, float $longitude): ?GeocodedLocation;
}
