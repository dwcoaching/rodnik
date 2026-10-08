<?php

declare(strict_types=1);

namespace App\Support\Docs;

/**
 * Server-side port of routePreview() and mapCoordinates() from
 * resources/js/libraryPreview.js, drawing into a 100×76 viewBox.
 */
final class RoutePreview
{
    /** A $maxPoints limit keeps long lists of small thumbnails light; it keeps both ends. */
    public static function path(mixed $points, int $maxPoints = PHP_INT_MAX): string
    {
        if (! is_array($points)) {
            return '';
        }

        $coordinates = array_values(array_filter($points, fn (mixed $point): bool => is_array($point)
            && array_is_list($point)
            && count($point) >= 2
            && self::isCoordinate($point[0], 180)
            && self::isCoordinate($point[1], 90)));

        if (count($coordinates) < 2) {
            return '';
        }

        $limit = max($maxPoints, 2);

        if (count($coordinates) > $limit) {
            $last = count($coordinates) - 1;
            $coordinates = array_map(
                fn (int $index): array => $coordinates[(int) round($index * $last / ($limit - 1))],
                range(0, $limit - 1),
            );
        }

        $longitude = $coordinates[0][0];
        $projected = array_map(function (array $point) use ($longitude): array {
            $latitude = deg2rad(max(-85, min(85, $point[1])));

            return [fmod($point[0] - $longitude + 540, 360) - 180, rad2deg(log(tan(M_PI / 4 + $latitude / 2)))];
        }, $coordinates);

        $xs = array_column($projected, 0);
        $ys = array_column($projected, 1);
        $minX = min($xs);
        $minY = min($ys);
        $width = max($xs) - $minX;
        $height = max($ys) - $minY;
        $scale = min(76 / max($width, 0.000001), 52 / max($height, 0.000001));

        return implode(' ', array_map(fn (array $point, int $index): string => sprintf(
            '%s%s,%s',
            $index === 0 ? 'M' : 'L',
            number_format(50 + ($point[0] - $minX - $width / 2) * $scale, 1, '.', ''),
            number_format(38 - ($point[1] - $minY - $height / 2) * $scale, 1, '.', ''),
        ), $projected, array_keys($projected)));
    }

    public static function coordinates(mixed $center): string
    {
        if (! is_array($center) || ! array_is_list($center) || count($center) < 2
            || ! array_all($center, fn (mixed $value): bool => self::isCoordinate($value, INF))) {
            return '';
        }

        [$longitude, $latitude] = $center;

        return sprintf(
            '%s° %s, %s° %s',
            number_format(abs($latitude), 2, '.', ''),
            $latitude < 0 ? 'S' : 'N',
            number_format(abs($longitude), 2, '.', ''),
            $longitude < 0 ? 'W' : 'E',
        );
    }

    private static function isCoordinate(mixed $value, float $limit): bool
    {
        return (is_int($value) || is_float($value)) && is_finite((float) $value) && abs($value) <= $limit;
    }
}
