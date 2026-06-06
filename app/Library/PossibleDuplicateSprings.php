<?php

namespace App\Library;

use App\Models\Spring;
use Illuminate\Support\Collection;

class PossibleDuplicateSprings
{
    public const RADII = [50, 100, 500];

    public const LIMITS = [100, 500, 1000, 10000];

    public const TIME_LIMIT_SECONDS = 20;

    public static function normalizeRadius(mixed $radius): int
    {
        $radius = (int) $radius;

        return in_array($radius, self::RADII, true) ? $radius : self::RADII[0];
    }

    public static function normalizeLimit(mixed $limit): int
    {
        $limit = (int) $limit;

        return in_array($limit, self::LIMITS, true) ? $limit : self::LIMITS[0];
    }

    public static function candidates(int $radius, int $limit = self::LIMITS[0]): Collection
    {
        return self::scan($radius, $limit)['duplicates'];
    }

    public static function scanCandidates(int $radius, int $limit = self::LIMITS[0]): Collection
    {
        return self::scan($radius, $limit)['duplicates'];
    }

    public static function scan(int $radius, int $limit = self::LIMITS[0], ?float $timeLimitSeconds = null): array
    {
        $radius = self::normalizeRadius($radius);
        $limit = self::normalizeLimit($limit);
        $distance = new HaversineDistance();
        $duplicates = collect();
        $startedAt = microtime(true);

        $rodnikSources = Spring::query()
            ->whereNull('osm_node_id')
            ->whereNull('osm_way_id')
            ->whereNull('hidden_at')
            ->whereNull('redirect_to_spring_id')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->select(['id', 'name', 'type', 'latitude', 'longitude'])
            ->orderBy('id')
            ->cursor();

        foreach ($rodnikSources as $rodnik) {
            if (self::timeLimitExceeded($startedAt, $timeLimitSeconds)) {
                return self::result($duplicates, $startedAt, timedOut: true);
            }

            $closestDuplicate = null;

            foreach (self::nearbyOsmSources($rodnik, $radius) as $osm) {
                $meters = $distance->metersBetweenCoordinates(
                    $rodnik->latitude,
                    $rodnik->longitude,
                    $osm->latitude,
                    $osm->longitude,
                );

                if ($meters === null || $meters > $radius) {
                    continue;
                }

                if ($closestDuplicate !== null && $meters >= $closestDuplicate->distance_meters) {
                    continue;
                }

                $closestDuplicate = (object) [
                    'rodnik_id' => $rodnik->id,
                    'rodnik_name' => $rodnik->name,
                    'rodnik_type' => $rodnik->type,
                    'rodnik_latitude' => $rodnik->latitude,
                    'rodnik_longitude' => $rodnik->longitude,
                    'osm_id' => $osm->id,
                    'osm_name' => $osm->name,
                    'osm_type' => $osm->type,
                    'osm_latitude' => $osm->latitude,
                    'osm_longitude' => $osm->longitude,
                    'osm_node_id' => $osm->osm_node_id,
                    'osm_way_id' => $osm->osm_way_id,
                    'distance_meters' => $meters,
                ];

                if (self::timeLimitExceeded($startedAt, $timeLimitSeconds)) {
                    if ($closestDuplicate !== null) {
                        $duplicates->push($closestDuplicate);
                    }

                    return self::result($duplicates, $startedAt, timedOut: true);
                }
            }

            if ($closestDuplicate === null) {
                continue;
            }

            $duplicates->push($closestDuplicate);

            if ($duplicates->count() >= $limit) {
                return self::result($duplicates, $startedAt, limitReached: true);
            }
        }

        return self::result($duplicates, $startedAt);
    }

    private static function result(Collection $duplicates, float $startedAt, bool $timedOut = false, bool $limitReached = false): array
    {
        return [
            'duplicates' => self::sortByDistance($duplicates),
            'timed_out' => $timedOut,
            'limit_reached' => $limitReached,
            'elapsed_seconds' => microtime(true) - $startedAt,
        ];
    }

    private static function timeLimitExceeded(float $startedAt, ?float $timeLimitSeconds): bool
    {
        return $timeLimitSeconds !== null && microtime(true) - $startedAt >= $timeLimitSeconds;
    }

    private static function sortByDistance(Collection $duplicates): Collection
    {
        return $duplicates
            ->sortBy('distance_meters')
            ->values();
    }

    private static function nearbyOsmSources(Spring $rodnik, int $radius): Collection
    {
        $latitude = (float) $rodnik->latitude;
        $longitude = (float) $rodnik->longitude;
        $latitudeDelta = $radius / 111320;
        $cosLatitude = abs(cos(deg2rad($latitude)));
        $longitudeDelta = $cosLatitude > 0.000001
            ? min(180, $latitudeDelta / $cosLatitude)
            : 180;

        $query = Spring::query()
            ->where(function ($query) {
                $query->whereNotNull('osm_node_id')
                    ->orWhereNotNull('osm_way_id');
            })
            ->whereNull('hidden_at')
            ->whereNull('redirect_to_spring_id')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereBetween('latitude', [
                max(-90, $latitude - $latitudeDelta),
                min(90, $latitude + $latitudeDelta),
            ])
            ->select([
                'id',
                'name',
                'type',
                'latitude',
                'longitude',
                'osm_node_id',
                'osm_way_id',
            ])
            ->orderBy('id');

        if ($longitudeDelta >= 180) {
            return $query->get();
        }

        $minLongitude = $longitude - $longitudeDelta;
        $maxLongitude = $longitude + $longitudeDelta;

        $query->where(function ($query) use ($minLongitude, $maxLongitude) {
            if ($minLongitude < -180) {
                return $query
                    ->whereBetween('longitude', [$minLongitude + 360, 180])
                    ->orWhereBetween('longitude', [-180, $maxLongitude]);
            }

            if ($maxLongitude > 180) {
                return $query
                    ->whereBetween('longitude', [$minLongitude, 180])
                    ->orWhereBetween('longitude', [-180, $maxLongitude - 360]);
            }

            return $query->whereBetween('longitude', [$minLongitude, $maxLongitude]);
        });

        return $query->get();
    }
}
