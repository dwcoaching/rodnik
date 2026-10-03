<?php

declare(strict_types=1);

namespace App\Services\Geocoding;

/** Packed country rings, with latitude bands to avoid scanning entire coastlines. */
final class CountryGeometry
{
    private const EPSILON = 0.0000000001;

    private function __construct(private readonly array $polygons, private readonly array $bbox) {}

    /** Geometry coordinates are validated by GeoJsonReverseGeocoder before compilation. */
    public static function fromArray(array $geometry): self
    {
        $polygons = $geometry['type'] === 'Polygon' ? [$geometry['coordinates']] : $geometry['coordinates'];
        $compiled = [];

        foreach ($polygons as $polygon) {
            $rings = [];

            foreach ($polygon as $ring) {
                $points = '';
                $minimumX = $maximumX = (float) $ring[0][0];
                $minimumY = $maximumY = (float) $ring[0][1];

                foreach ($ring as $position) {
                    $x = (float) $position[0];
                    $y = (float) $position[1];
                    $points .= pack('e2', $x, $y);
                    $minimumX = min($minimumX, $x);
                    $maximumX = max($maximumX, $x);
                    $minimumY = min($minimumY, $y);
                    $maximumY = max($maximumY, $y);
                }

                $bandCount = min(128, max(1, intdiv(count($ring), 32)));
                $bands = array_fill(0, $bandCount, '');
                $height = $maximumY - $minimumY;

                foreach ($ring as $current => $position) {
                    $previous = $current === 0 ? count($ring) - 1 : $current - 1;
                    $startY = (float) $ring[$previous][1];
                    $endY = (float) $position[1];
                    $startBand = self::band(min($startY, $endY), $minimumY, $height, $bandCount);
                    $endBand = self::band(max($startY, $endY), $minimumY, $height, $bandCount);
                    $edge = pack('V', $current);

                    for ($band = $startBand; $band <= $endBand; $band++) {
                        $bands[$band] .= $edge;
                    }
                }

                $rings[] = ['bbox' => [$minimumX, $minimumY, $maximumX, $maximumY], 'points' => $points, 'bands' => $bands];
            }

            $compiled[] = ['bbox' => $rings[0]['bbox'], 'rings' => $rings];
        }

        $bbox = $compiled[0]['bbox'];

        foreach ($compiled as $polygon) {
            $bbox = [min($bbox[0], $polygon['bbox'][0]), min($bbox[1], $polygon['bbox'][1]), max($bbox[2], $polygon['bbox'][2]), max($bbox[3], $polygon['bbox'][3])];
        }

        return new self($compiled, $bbox);
    }

    public function contains(float $longitude, float $latitude): bool
    {
        if (! self::bboxContains($this->bbox, $longitude, $latitude)) {
            return false;
        }

        foreach ($this->polygons as $polygon) {
            if (! self::bboxContains($polygon['bbox'], $longitude, $latitude) || ! self::ringContains($polygon['rings'][0], $longitude, $latitude)) {
                continue;
            }

            $inHole = false;

            foreach (array_slice($polygon['rings'], 1) as $hole) {
                if (self::ringContains($hole, $longitude, $latitude)) {
                    $inHole = true;

                    break;
                }
            }

            if (! $inHole) {
                return true;
            }
        }

        return false;
    }

    private static function band(float $y, float $minimumY, float $height, int $count): int
    {
        return $height === 0.0 ? 0 : min($count - 1, max(0, (int) floor(($y - $minimumY) / $height * $count)));
    }

    private static function bboxContains(array $bbox, float $longitude, float $latitude): bool
    {
        return $longitude >= $bbox[0] && $longitude <= $bbox[2] && $latitude >= $bbox[1] && $latitude <= $bbox[3];
    }

    private static function ringContains(array $ring, float $longitude, float $latitude): bool
    {
        if (! self::bboxContains($ring['bbox'], $longitude, $latitude)) {
            return false;
        }

        $band = self::band($latitude, $ring['bbox'][1], $ring['bbox'][3] - $ring['bbox'][1], count($ring['bands']));
        $edges = $ring['bands'][$band];
        $pointCount = intdiv(mb_strlen($ring['points'], '8bit'), 16);
        $inside = false;

        for ($offset = 0, $length = mb_strlen($edges, '8bit'); $offset < $length; $offset += 4) {
            $current = unpack('V', $edges, $offset)[1];
            $previous = $current === 0 ? $pointCount - 1 : $current - 1;
            [$currentX, $currentY] = array_values(unpack('e2', $ring['points'], $current * 16));
            [$previousX, $previousY] = array_values(unpack('e2', $ring['points'], $previous * 16));
            $crossProduct = ($latitude - $previousY) * ($currentX - $previousX) - ($longitude - $previousX) * ($currentY - $previousY);

            if (abs($crossProduct) <= self::EPSILON
                && $longitude >= min($previousX, $currentX) - self::EPSILON
                && $longitude <= max($previousX, $currentX) + self::EPSILON
                && $latitude >= min($previousY, $currentY) - self::EPSILON
                && $latitude <= max($previousY, $currentY) + self::EPSILON) {
                return true;
            }

            if (($currentY > $latitude) !== ($previousY > $latitude)
                && $longitude < (($previousX - $currentX) * ($latitude - $currentY) / ($previousY - $currentY) + $currentX)) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }
}
