<?php

declare(strict_types=1);

namespace App\Services\Geography;

/**
 * Map viewports as [west, south, east, north] degrees. West is in [-180, 180)
 * and east >= west, so a box crossing the antimeridian has east > 180.
 */
final class PlaceBounds
{
    /** Parts smaller than this share of the main part extend the box only next to it. */
    private const MINIMUM_AREA_SHARE = 0.005;

    private const MINIMUM_THRESHOLD = 2.0;

    /** Archipelagos of islands under 1,000 km² lie farther apart, as in the Austral Islands. */
    private const SMALL_ISLAND_AREA = 1000.0;

    private const SMALL_ISLAND_THRESHOLD = 5.0;

    private const SQUARE_KILOMETRES_PER_SQUARE_DEGREE = 111.32 ** 2;

    /**
     * Summarize the outer ring of every polygon in a GeoJSON geometry.
     *
     * @param  array<string,mixed>  $geometry
     * @return list<array{west:float,south:float,east:float,north:float,area:float}>
     */
    public static function parts(array $geometry): array
    {
        $polygons = match ($geometry['type'] ?? null) {
            'Polygon' => [$geometry['coordinates'] ?? null],
            'MultiPolygon' => $geometry['coordinates'] ?? null,
            default => null,
        };
        if (! is_array($polygons)) {
            return [];
        }
        $parts = [];
        foreach ($polygons as $polygon) {
            if (is_array($polygon) && is_array($polygon[0] ?? null) && ($part = self::ring($polygon[0])) !== null) {
                $parts[] = $part;
            }
        }

        return $parts;
    }

    /**
     * Bounding box of the main cluster: the largest part plus significant
     * parts near the growing box, and small parts right next to the largest
     * part, so remote territories do not stretch the viewport.
     *
     * @param  list<array{west:float,south:float,east:float,north:float,area:float}>  $parts
     * @return null|array{0:float,1:float,2:float,3:float}
     */
    public static function mainCluster(array $parts): ?array
    {
        if ($parts === []) {
            return null;
        }
        $mainIndex = 0;
        foreach ($parts as $index => $part) {
            if ($part['area'] > $parts[$mainIndex]['area']) {
                $mainIndex = $index;
            }
        }
        $main = $parts[$mainIndex];
        unset($parts[$mainIndex]);
        $floor = $main['area'] * self::SQUARE_KILOMETRES_PER_SQUARE_DEGREE < self::SMALL_ISLAND_AREA
            ? self::SMALL_ISLAND_THRESHOLD
            : self::MINIMUM_THRESHOLD;
        // Small parts are measured from the largest part alone, so chains of islets never grow the box.
        $reach = min(max(0.1 * self::diagonal($main), 0.25), 3.0);
        $candidates = [];
        $nearby = [];
        foreach (self::aligned($parts, $main) as $part) {
            if ($part['area'] >= self::MINIMUM_AREA_SHARE * $main['area']) {
                $candidates[] = $part;
            } elseif (self::distance($part, $main) <= $reach) {
                $nearby[] = $part;
            }
        }
        [$box] = self::grow($main, $candidates, $floor);
        foreach ($nearby as $part) {
            $box = self::union($box, $part);
        }

        return self::normalize($box['west'], $box['south'], $box['east'], $box['north']);
    }

    /**
     * Bounding box of the main cluster of settlements, grown like polygon
     * parts from the most populous one. Every settlement counts, since
     * villages rather than cities mark a region's edges, and the most
     * populous cluster wins, so one misfiled city cannot take over.
     *
     * @param  list<array{0:float,1:float,2:int}>  $settlements  latitude, longitude, population
     * @return null|array{0:float,1:float,2:float,3:float}
     */
    public static function fromSettlements(array $settlements): ?array
    {
        $parts = array_map(static fn (array $settlement): array => [
            'west' => $settlement[1], 'south' => $settlement[0], 'east' => $settlement[1], 'north' => $settlement[0],
            'area' => (float) max($settlement[2], 1),
        ], $settlements);
        usort($parts, static fn (array $first, array $second): int => $second['area'] <=> $first['area']);
        $best = null;
        while (($seed = array_shift($parts)) !== null) {
            [$box, $population, $parts] = self::grow($seed, self::aligned($parts, $seed), self::MINIMUM_THRESHOLD);
            if ($best === null || $population > $best[1]) {
                $best = [$box, $population];
            }
        }

        return $best === null ? null : self::normalize($best[0]['west'], $best[0]['south'], $best[0]['east'], $best[0]['north']);
    }

    /**
     * @param  array<string,mixed>  $geometry
     * @return null|array{0:float,1:float,2:float,3:float}
     */
    public static function fromGeometry(array $geometry): ?array
    {
        return self::mainCluster(self::parts($geometry));
    }

    /**
     * Grow a box by the candidates near it. The distance threshold is half the
     * box's diagonal, at least the floor, so it widens as candidates join.
     * Also returns the joined area, or population, and the parts left out.
     *
     * @param  array{west:float,south:float,east:float,north:float,area:float}  $seed
     * @param  list<array{west:float,south:float,east:float,north:float,area:float}>  $candidates
     * @return array{0:array{west:float,south:float,east:float,north:float},1:float,2:list<array{west:float,south:float,east:float,north:float,area:float}>}
     */
    private static function grow(array $seed, array $candidates, float $floor): array
    {
        $box = $seed;
        $area = $seed['area'];
        do {
            $grown = false;
            foreach ($candidates as $index => $part) {
                if (self::distance($part, $box) <= max($floor, 0.5 * self::diagonal($box))) {
                    $box = self::union($box, $part);
                    $area += $part['area'];
                    unset($candidates[$index]);
                    $grown = true;
                }
            }
        } while ($grown);

        return [$box, $area, array_values($candidates)];
    }

    /**
     * Shift parts by whole turns to the side of the antimeridian nearest the main part.
     *
     * @param  array<array{west:float,south:float,east:float,north:float,area:float}>  $parts
     * @param  array{west:float,east:float}  $main
     * @return list<array{west:float,south:float,east:float,north:float,area:float}>
     */
    private static function aligned(array $parts, array $main): array
    {
        $center = ($main['west'] + $main['east']) / 2;

        return array_values(array_map(static function (array $part) use ($center): array {
            $shift = 360 * round(($center - ($part['west'] + $part['east']) / 2) / 360);

            return [...$part, 'west' => $part['west'] + $shift, 'east' => $part['east'] + $shift];
        }, $parts));
    }

    /** @return null|array{west:float,south:float,east:float,north:float,area:float} */
    private static function ring(array $ring): ?array
    {
        $west = $east = null;
        foreach ($ring as $position) {
            if (($point = self::position($position)) !== null) {
                $west = min($west ?? $point[0], $point[0]);
                $east = max($east ?? $point[0], $point[0]);
            }
        }
        if ($west === null) {
            return null;
        }
        // A single ring this wide runs across the antimeridian.
        $wraps = $east - $west > 180;
        $west = $south = $east = $north = null;
        $first = $previous = null;
        $twiceArea = 0.0;
        foreach ($ring as $position) {
            if (($point = self::position($position)) === null) {
                continue;
            }
            if ($wraps && $point[0] < 0) {
                $point[0] += 360;
            }
            $west = min($west ?? $point[0], $point[0]);
            $east = max($east ?? $point[0], $point[0]);
            $south = min($south ?? $point[1], $point[1]);
            $north = max($north ?? $point[1], $point[1]);
            if ($previous !== null) {
                $twiceArea += $previous[0] * $point[1] - $point[0] * $previous[1];
            }
            $first ??= $point;
            $previous = $point;
        }
        $twiceArea += $previous[0] * $first[1] - $first[0] * $previous[1];

        return [
            'west' => $west, 'south' => $south, 'east' => $east, 'north' => $north,
            'area' => abs($twiceArea) / 2 * cos(deg2rad(($south + $north) / 2)),
        ];
    }

    /** @return null|array{0:float,1:float} */
    private static function position(mixed $position): ?array
    {
        if (! is_array($position) || ! is_numeric($position[0] ?? null) || ! is_numeric($position[1] ?? null)) {
            return null;
        }
        $longitude = (float) $position[0];
        $latitude = (float) $position[1];

        return $longitude >= -180 && $longitude <= 180 && $latitude >= -90 && $latitude <= 90 ? [$longitude, $latitude] : null;
    }

    /** @param array{west:float,south:float,east:float,north:float} $box */
    private static function diagonal(array $box): float
    {
        return hypot(($box['east'] - $box['west']) * cos(deg2rad(($box['south'] + $box['north']) / 2)), $box['north'] - $box['south']);
    }

    /**
     * @param  array{west:float,south:float,east:float,north:float}  $box
     * @param  array{west:float,south:float,east:float,north:float}  $part
     * @return array{west:float,south:float,east:float,north:float}
     */
    private static function union(array $box, array $part): array
    {
        return [
            'west' => min($box['west'], $part['west']), 'south' => min($box['south'], $part['south']),
            'east' => max($box['east'], $part['east']), 'north' => max($box['north'], $part['north']),
        ];
    }

    /**
     * @param  array{west:float,south:float,east:float,north:float}  $part
     * @param  array{west:float,south:float,east:float,north:float}  $box
     */
    private static function distance(array $part, array $box): float
    {
        $latitude = ($part['south'] + $part['north'] + $box['south'] + $box['north']) / 4;
        $dx = max(0, $part['west'] - $box['east'], $box['west'] - $part['east']) * cos(deg2rad($latitude));
        $dy = max(0, $part['south'] - $box['north'], $box['south'] - $part['north']);

        return hypot($dx, $dy);
    }

    /** @return array{0:float,1:float,2:float,3:float} */
    private static function normalize(float $west, float $south, float $east, float $north): array
    {
        // Antarctica's ring stops just short of a full circle.
        if ($east - $west >= 359) {
            return [-180.0, round($south, 7), 180.0, round($north, 7)];
        }
        $shift = 360 * floor(($west + 180) / 360);

        return [round($west - $shift, 7), round($south, 7), round($east - $shift, 7), round($north, 7)];
    }
}
