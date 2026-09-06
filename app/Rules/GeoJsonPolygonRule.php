<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use JsonException;
use stdClass;

final class GeoJsonPolygonRule implements ValidationRule
{
    public const MAX_BYTES = 1_048_576;

    public const MAX_VERTICES = 50_000;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || mb_strlen($value, '8bit') > self::MAX_BYTES) {
            $fail('The :attribute must be a GeoJSON polygon no larger than 1 MiB.');

            return;
        }

        try {
            $geometry = json_decode($value, false, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $fail('The :attribute must contain valid GeoJSON.');

            return;
        }

        if (! $this->validGeometry($geometry)) {
            $fail('The :attribute must be a Polygon or MultiPolygon with closed rings, valid longitude/latitude pairs, and at most 50000 vertices.');
        }
    }

    private function validGeometry(mixed $geometry): bool
    {
        if (! $geometry instanceof stdClass
            || count(get_object_vars($geometry)) !== 2
            || ! in_array($geometry->type ?? null, ['Polygon', 'MultiPolygon'], true)
            || ! $this->nonEmptyList($geometry->coordinates ?? null)) {
            return false;
        }

        $polygons = $geometry->type === 'Polygon' ? [$geometry->coordinates] : $geometry->coordinates;
        $vertices = 0;

        foreach ($polygons as $polygon) {
            if (! $this->nonEmptyList($polygon)) {
                return false;
            }

            foreach ($polygon as $ring) {
                if (! $this->validRing($ring, $vertices)) {
                    return false;
                }
            }
        }

        return true;
    }

    private function validRing(mixed $ring, int &$vertices): bool
    {
        if (! $this->nonEmptyList($ring) || count($ring) < 4) {
            return false;
        }

        $vertices += count($ring);

        if ($vertices > self::MAX_VERTICES) {
            return false;
        }

        $distinctPositions = [];

        foreach ($ring as $position) {
            if (! is_array($position) || ! array_is_list($position) || count($position) !== 2
                || ! $this->validNumber($position[0], 180)
                || ! $this->validNumber($position[1], 90)) {
                return false;
            }

            if (count($distinctPositions) < 3 && ! in_array($position, $distinctPositions)) {
                $distinctPositions[] = $position;
            }
        }

        $first = $ring[0];
        $last = $ring[array_key_last($ring)];

        return count($distinctPositions) >= 3
            && (float) $first[0] === (float) $last[0]
            && (float) $first[1] === (float) $last[1];
    }

    private function validNumber(mixed $number, int $limit): bool
    {
        return (is_int($number) || is_float($number))
            && is_finite((float) $number)
            && $number >= -$limit
            && $number <= $limit;
    }

    private function nonEmptyList(mixed $value): bool
    {
        return is_array($value) && array_is_list($value) && $value !== [];
    }
}
