<?php

declare(strict_types=1);

namespace App\Library;

use GEOSGeometry;
use GEOSWKTReader;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class TrackPolygonArea
{
    private function __construct(
        private readonly GEOSGeometry $geometry,
        private readonly GEOSWKTReader $reader,
    ) {}

    /** @param array<string, mixed> $geometry */
    public static function fromArray(array $geometry): self
    {
        if (! extension_loaded('geos')) {
            throw new RuntimeException('The GEOS extension is required to filter reports along tracks.');
        }

        $coordinates = self::nonEmptyList($geometry['coordinates'] ?? null);
        $wkt = match ($geometry['type'] ?? null) {
            'Polygon' => 'POLYGON '.self::polygonText($coordinates),
            'MultiPolygon' => 'MULTIPOLYGON ('.implode(', ', array_map(self::polygonText(...), $coordinates)).')',
            default => throw new InvalidArgumentException('Track geometry must be a Polygon or MultiPolygon.'),
        };

        $reader = new GEOSWKTReader;

        try {
            $polygon = $reader->read($wkt);
        } catch (Throwable $exception) {
            throw new InvalidArgumentException('Track polygon could not be read by GEOS.', previous: $exception);
        }

        if (! $polygon instanceof GEOSGeometry || $polygon->isEmpty()) {
            throw new InvalidArgumentException('Track polygon must not be empty.');
        }

        $validity = $polygon->checkValidity();

        if (($validity['valid'] ?? false) !== true) {
            throw new InvalidArgumentException('Track polygon is invalid: '.($validity['reason'] ?? 'unknown reason').'.');
        }

        return new self($polygon, $reader);
    }

    public function contains(float $longitude, float $latitude): bool
    {
        $point = $this->reader->read('POINT ('.self::positionText([$longitude, $latitude]).')');

        return $this->geometry->covers($point);
    }

    private static function polygonText(mixed $polygon): string
    {
        return '('.implode(', ', array_map(self::ringText(...), self::nonEmptyList($polygon))).')';
    }

    private static function ringText(mixed $ring): string
    {
        $positions = self::nonEmptyList($ring);

        if (count($positions) < 4) {
            throw new InvalidArgumentException('Track polygon rings must contain at least four positions.');
        }

        return '('.implode(', ', array_map(self::positionText(...), $positions)).')';
    }

    private static function positionText(mixed $position): string
    {
        if (! is_array($position) || ! array_is_list($position) || count($position) !== 2) {
            throw new InvalidArgumentException('Track polygon positions must be longitude/latitude pairs.');
        }

        foreach ($position as $index => $coordinate) {
            if ((! is_int($coordinate) && ! is_float($coordinate))
                || ! is_finite((float) $coordinate)
                || abs($coordinate) > ($index === 0 ? 180 : 90)) {
                throw new InvalidArgumentException('Track polygon coordinates must be finite, valid longitude/latitude values.');
            }
        }

        return json_encode($position[0], JSON_THROW_ON_ERROR).' '.json_encode($position[1], JSON_THROW_ON_ERROR);
    }

    /** @return non-empty-list<mixed> */
    private static function nonEmptyList(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value) || $value === []) {
            throw new InvalidArgumentException('Track polygon coordinates must contain non-empty lists.');
        }

        return $value;
    }
}
