<?php

namespace App\Library;

use App\Models\Spring;

class HaversineDistance
{
    private const EARTH_RADIUS_METERS = 6371000;

    public function metersBetweenSprings(Spring $from, Spring $to): ?float
    {
        return $this->metersBetweenCoordinates(
            $from->latitude,
            $from->longitude,
            $to->latitude,
            $to->longitude,
        );
    }

    public function metersBetweenCoordinates($fromLatitude, $fromLongitude, $toLatitude, $toLongitude): ?float
    {
        if ($fromLatitude === null || $fromLongitude === null || $toLatitude === null || $toLongitude === null) {
            return null;
        }

        $fromLatitude = (float) $fromLatitude;
        $fromLongitude = (float) $fromLongitude;
        $toLatitude = (float) $toLatitude;
        $toLongitude = (float) $toLongitude;

        $latitudeDelta = deg2rad($toLatitude - $fromLatitude);
        $longitudeDelta = deg2rad($toLongitude - $fromLongitude);
        $fromLatitude = deg2rad($fromLatitude);
        $toLatitude = deg2rad($toLatitude);

        $a = sin($latitudeDelta / 2) ** 2
            + cos($fromLatitude) * cos($toLatitude) * sin($longitudeDelta / 2) ** 2;
        $a = min(1, max(0, $a));

        return self::EARTH_RADIUS_METERS * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    public function formatMeters(?float $meters): ?string
    {
        if ($meters === null) {
            return null;
        }

        return number_format((int) round($meters), 0, '.', ' ') . ' m';
    }
}
