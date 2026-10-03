<?php

declare(strict_types=1);

namespace App\Services\Geocoding;

use InvalidArgumentException;

final class GeoJsonPolygonValidator
{
    public static function validate(array $geometry): void
    {
        if (! in_array($geometry['type'] ?? null, ['Polygon', 'MultiPolygon'], true)) {
            throw new InvalidArgumentException('Border geometries must be Polygon or MultiPolygon.');
        }

        $coordinates = $geometry['coordinates'] ?? null;

        if (! is_array($coordinates) || ! array_is_list($coordinates) || $coordinates === []) {
            throw new InvalidArgumentException('Country boundaries must contain non-empty coordinates.');
        }

        $polygons = $geometry['type'] === 'Polygon' ? [$coordinates] : $coordinates;

        foreach ($polygons as $polygon) {
            if (! is_array($polygon) || ! array_is_list($polygon) || $polygon === []) {
                throw new InvalidArgumentException('Country polygons must contain at least one linear ring.');
            }

            foreach ($polygon as $ring) {
                if (! is_array($ring) || ! array_is_list($ring) || count($ring) < 4) {
                    throw new InvalidArgumentException('Country linear rings must contain at least four positions.');
                }

                $first = null;
                $last = null;
                $distinct = [];

                foreach ($ring as $position) {
                    if (! is_array($position) || ! array_is_list($position) || count($position) < 2 || ! is_numeric($position[0]) || ! is_numeric($position[1])) {
                        throw new InvalidArgumentException('GeoJSON positions must contain numeric longitude and latitude.');
                    }

                    $longitude = (float) $position[0];
                    $latitude = (float) $position[1];

                    if (! is_finite($latitude) || ! is_finite($longitude) || abs($latitude) > 90 || abs($longitude) > 180) {
                        throw new InvalidArgumentException('Geocoding coordinates must be finite WGS84 latitude/longitude values.');
                    }

                    $last = [$longitude, $latitude];
                    $first ??= $last;

                    // Three different positions are enough to validate a ring.
                    // Keeping a second copy of every coordinate would double
                    // memory when large border layers replace an active index.
                    if (count($distinct) < 3 && ! in_array($last, $distinct, true)) {
                        $distinct[] = $last;
                    }
                }

                if ($first !== $last || count($distinct) < 3) {
                    throw new InvalidArgumentException('Country linear rings must be closed and contain at least three distinct positions.');
                }
            }
        }
    }
}
