<?php

declare(strict_types=1);

namespace App\Library;

use InvalidArgumentException;
use RuntimeException;

final class GeoJsonArea
{
    private const EPSILON = 0.0000000001;

    /**
     * @param  list<array{bbox: array{float, float, float, float}, rings: list<array{bbox: array{float, float, float, float}, points: list<array{float, float}>}>}>  $polygons
     */
    private function __construct(private readonly array $polygons) {}

    public static function fromResource(string $path): self
    {
        return self::fromFile(resource_path($path));
    }

    public static function fromFile(string $path): self
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Could not read GeoJSON file [{$path}].");
        }

        return self::fromJson($contents);
    }

    public static function fromJson(string $json): self
    {
        return self::fromArray(json_decode($json, true, 512, JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<string, mixed>  $geoJson
     */
    public static function fromArray(array $geoJson): self
    {
        return new self(self::compileGeometry($geoJson));
    }

    public function contains(float $longitude, float $latitude): bool
    {
        foreach ($this->polygons as $polygon) {
            if (! self::bboxContains($polygon['bbox'], $longitude, $latitude)) {
                continue;
            }

            if (self::polygonContains($polygon, $longitude, $latitude)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $geometry
     * @return list<array{bbox: array{float, float, float, float}, rings: list<array{bbox: array{float, float, float, float}, points: list<array{float, float}>}>}>
     */
    private static function compileGeometry(array $geometry): array
    {
        $type = $geometry['type'] ?? null;

        return match ($type) {
            'FeatureCollection' => self::compileFeatureCollection($geometry),
            'Feature' => self::compileGeometry(self::arrayValue($geometry, 'geometry')),
            'Polygon' => [self::compilePolygon(self::arrayValue($geometry, 'coordinates'))],
            'MultiPolygon' => array_map(
                fn (array $polygon): array => self::compilePolygon($polygon),
                self::arrayValue($geometry, 'coordinates'),
            ),
            default => throw new InvalidArgumentException("Unsupported GeoJSON geometry type [{$type}]."),
        };
    }

    /**
     * @param  array<string, mixed>  $featureCollection
     * @return list<array{bbox: array{float, float, float, float}, rings: list<array{bbox: array{float, float, float, float}, points: list<array{float, float}>}>}>
     */
    private static function compileFeatureCollection(array $featureCollection): array
    {
        $polygons = [];

        foreach (self::arrayValue($featureCollection, 'features') as $feature) {
            array_push($polygons, ...self::compileGeometry($feature));
        }

        return $polygons;
    }

    /**
     * @param  list<list<array{0: float|int|string, 1: float|int|string}>>  $rings
     * @return array{bbox: array{float, float, float, float}, rings: list<array{bbox: array{float, float, float, float}, points: list<array{float, float}>}>}
     */
    private static function compilePolygon(array $rings): array
    {
        $compiledRings = array_map(
            fn (array $ring): array => self::compileRing($ring),
            $rings,
        );

        if ($compiledRings === []) {
            throw new InvalidArgumentException('GeoJSON polygon must contain at least one ring.');
        }

        return [
            'bbox' => $compiledRings[0]['bbox'],
            'rings' => $compiledRings,
        ];
    }

    /**
     * @param  list<array{0: float|int|string, 1: float|int|string}>  $points
     * @return array{bbox: array{float, float, float, float}, points: list<array{float, float}>}
     */
    private static function compileRing(array $points): array
    {
        if (count($points) < 4) {
            throw new InvalidArgumentException('GeoJSON linear ring must contain at least four positions.');
        }

        $compiled = array_map(
            fn (array $point): array => [(float) $point[0], (float) $point[1]],
            $points,
        );

        return [
            'bbox' => self::bboxForRing($compiled),
            'points' => $compiled,
        ];
    }

    /**
     * @param  list<array{float, float}>  $points
     * @return array{float, float, float, float}
     */
    private static function bboxForRing(array $points): array
    {
        [$minX, $minY] = $points[0];
        $maxX = $minX;
        $maxY = $minY;

        foreach ($points as [$x, $y]) {
            $minX = min($minX, $x);
            $minY = min($minY, $y);
            $maxX = max($maxX, $x);
            $maxY = max($maxY, $y);
        }

        return [$minX, $minY, $maxX, $maxY];
    }

    /**
     * @param  array{bbox: array{float, float, float, float}, rings: list<array{bbox: array{float, float, float, float}, points: list<array{float, float}>}>}  $polygon
     */
    private static function polygonContains(array $polygon, float $longitude, float $latitude): bool
    {
        $outerRing = $polygon['rings'][0];

        if (! self::ringContains($outerRing, $longitude, $latitude)) {
            return false;
        }

        foreach (array_slice($polygon['rings'], 1) as $hole) {
            if (self::ringContains($hole, $longitude, $latitude)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array{bbox: array{float, float, float, float}, points: list<array{float, float}>}  $ring
     */
    private static function ringContains(array $ring, float $longitude, float $latitude): bool
    {
        if (! self::bboxContains($ring['bbox'], $longitude, $latitude)) {
            return false;
        }

        $inside = false;
        $points = $ring['points'];
        $pointCount = count($points);

        for ($current = 0, $previous = $pointCount - 1; $current < $pointCount; $previous = $current++) {
            [$currentX, $currentY] = $points[$current];
            [$previousX, $previousY] = $points[$previous];

            if (self::pointIsOnSegment($longitude, $latitude, $previousX, $previousY, $currentX, $currentY)) {
                return true;
            }

            if (
                (($currentY > $latitude) !== ($previousY > $latitude))
                && $longitude < (($previousX - $currentX) * ($latitude - $currentY) / ($previousY - $currentY) + $currentX)
            ) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    private static function pointIsOnSegment(
        float $pointX,
        float $pointY,
        float $startX,
        float $startY,
        float $endX,
        float $endY,
    ): bool {
        $crossProduct = ($pointY - $startY) * ($endX - $startX) - ($pointX - $startX) * ($endY - $startY);

        if (abs($crossProduct) > self::EPSILON) {
            return false;
        }

        return $pointX >= min($startX, $endX) - self::EPSILON
            && $pointX <= max($startX, $endX) + self::EPSILON
            && $pointY >= min($startY, $endY) - self::EPSILON
            && $pointY <= max($startY, $endY) + self::EPSILON;
    }

    /**
     * @param  array{float, float, float, float}  $bbox
     */
    private static function bboxContains(array $bbox, float $longitude, float $latitude): bool
    {
        return $longitude >= $bbox[0]
            && $longitude <= $bbox[2]
            && $latitude >= $bbox[1]
            && $latitude <= $bbox[3];
    }

    /**
     * @param  array<string, mixed>  $array
     */
    private static function arrayValue(array $array, string $key): array
    {
        if (! isset($array[$key]) || ! is_array($array[$key])) {
            throw new InvalidArgumentException("GeoJSON is missing array value [{$key}].");
        }

        return $array[$key];
    }
}
