<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use JsonException;
use stdClass;

final class GeoJsonTrackRule implements ValidationRule
{
    public const MAX_BYTES = 10_485_760;

    public const MAX_COORDINATES = 200_000;

    public const MAX_FEATURES = 10_000;

    private const MAX_DECODED_BYTES = 134_217_728;

    private const DECODE_MEMORY_RESERVE = 16_777_216;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || mb_strlen($value, '8bit') > self::MAX_BYTES) {
            $fail('The :attribute must be a GeoJSON track no larger than 10 MiB.');

            return;
        }

        if (! json_validate($value, 32)) {
            $fail('The :attribute must contain valid GeoJSON.');

            return;
        }

        if (! $this->withinDecodeBudget($value)) {
            $fail('The :attribute is too complex to process. Simplify the track or reduce its metadata and try again.');

            return;
        }

        try {
            $track = json_decode($value, false, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $fail('The :attribute must contain valid GeoJSON.');

            return;
        }

        if (! $this->validTrack($track)) {
            $fail('The :attribute must contain points or lines with valid longitude/latitude coordinates, scalar properties, at most 10000 features, and at most 200000 coordinates.');
        }
    }

    /**
     * Bound decoded containers, entries, and strings without allocating the JSON tree.
     * The estimates include spare array capacity and object-property table overhead.
     */
    private function withinDecodeBudget(string $json): bool
    {
        $memoryLimit = ini_parse_quantity((string) ini_get('memory_limit'));
        $budget = $memoryLimit > 0
            ? min(self::MAX_DECODED_BYTES, $memoryLimit - memory_get_usage(true) - self::DECODE_MEMORY_RESERVE)
            : self::MAX_DECODED_BYTES;
        $containers = [];
        $estimatedBytes = 0;
        $length = mb_strlen($json, '8bit');

        for ($offset = 0; $offset < $length; $offset++) {
            $character = $json[$offset];

            if ($character === '"') {
                $start = $offset++;

                while ($offset < $length) {
                    $offset += strcspn($json, '"\\', $offset);

                    if ($json[$offset] === '"') {
                        break;
                    }

                    $offset += 2;
                }

                $estimatedBytes += $offset - $start + 32;
            } elseif ($character === '[' || $character === '{') {
                $containers[] = $character;
                $estimatedBytes += $character === '[' ? 256 : 512;
            } elseif ($character === ']' || $character === '}') {
                array_pop($containers);
            } elseif ($character === ':') {
                $estimatedBytes += 128;
            } elseif ($character === ',' && end($containers) === '[') {
                $estimatedBytes += 32;
            }

            if ($estimatedBytes > $budget) {
                return false;
            }
        }

        return true;
    }

    private function validTrack(mixed $track): bool
    {
        if (! $this->hasOnlyKeys($track, ['type', 'features'])
            || ($track->type ?? null) !== 'FeatureCollection'
            || ! $this->nonEmptyList($track->features ?? null)
            || count($track->features) > self::MAX_FEATURES) {
            return false;
        }

        $coordinates = 0;

        foreach ($track->features as $feature) {
            if (! $this->hasOnlyKeys($feature, ['type', 'geometry', 'properties', 'id'])
                || ($feature->type ?? null) !== 'Feature'
                || ! property_exists($feature, 'properties')
                || ! $this->validProperties($feature->properties)
                || (property_exists($feature, 'id') && ! is_string($feature->id) && ! $this->finiteNumber($feature->id))
                || ! $this->validGeometry($feature->geometry ?? null, $coordinates)) {
                return false;
            }
        }

        return true;
    }

    private function validProperties(mixed $properties): bool
    {
        if ($properties === null) {
            return true;
        }

        if (! $properties instanceof stdClass) {
            return false;
        }

        foreach ($properties as $key => $value) {
            if ($key === 'geometry' || (in_array($key, ['name', 'desc'], true) && $value !== null && ! is_string($value))) {
                return false;
            }

            if ($value !== null && ! is_string($value) && ! is_bool($value) && ! $this->finiteNumber($value)) {
                return false;
            }
        }

        return true;
    }

    private function validGeometry(mixed $geometry, int &$coordinates): bool
    {
        if (! $this->hasOnlyKeys($geometry, ['type', 'coordinates'])) {
            return false;
        }

        return match ($geometry->type ?? null) {
            'Point' => $this->validPosition($geometry->coordinates ?? null, $coordinates),
            'LineString' => $this->validLine($geometry->coordinates ?? null, $coordinates),
            'MultiLineString' => $this->validLines($geometry->coordinates ?? null, $coordinates),
            default => false,
        };
    }

    private function validLines(mixed $lines, int &$coordinates): bool
    {
        if (! $this->nonEmptyList($lines)) {
            return false;
        }

        foreach ($lines as $line) {
            if (! $this->validLine($line, $coordinates)) {
                return false;
            }
        }

        return true;
    }

    private function validLine(mixed $line, int &$coordinates): bool
    {
        if (! $this->nonEmptyList($line) || count($line) < 2) {
            return false;
        }

        foreach ($line as $position) {
            if (! $this->validPosition($position, $coordinates)) {
                return false;
            }
        }

        return true;
    }

    private function validPosition(mixed $position, int &$coordinates): bool
    {
        if (! is_array($position) || ! array_is_list($position)
            || count($position) < 2 || count($position) > 4
            || ++$coordinates > self::MAX_COORDINATES) {
            return false;
        }

        foreach ($position as $index => $number) {
            if ($index === 2 && $number === null && count($position) === 4) {
                continue;
            }

            if (! $this->finiteNumber($number)) {
                return false;
            }
        }

        return $position[0] >= -180 && $position[0] <= 180
            && $position[1] >= -90 && $position[1] <= 90;
    }

    private function finiteNumber(mixed $number): bool
    {
        return (is_int($number) || is_float($number)) && is_finite((float) $number);
    }

    /** @param list<string> $keys */
    private function hasOnlyKeys(mixed $value, array $keys): bool
    {
        if (! $value instanceof stdClass) {
            return false;
        }

        foreach ($value as $key => $property) {
            if (! in_array($key, $keys, true)) {
                return false;
            }
        }

        return true;
    }

    private function nonEmptyList(mixed $value): bool
    {
        return is_array($value) && array_is_list($value) && $value !== [];
    }
}
