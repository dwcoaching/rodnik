<?php

declare(strict_types=1);

namespace App\Services\Geocoding;

use RuntimeException;

/**
 * A balanced KD tree on unit-sphere vectors. Chord distance has the same
 * ordering as great-circle distance, including at poles and the dateline.
 * Binary strings keep large GeoNames settlement catalogs comfortably
 * below a typical PHP memory limit; city labels are decoded only on lookup.
 */
final class CitySpatialIndex
{
    private const VERSION = 5;

    private const MAGIC = "RODNIK-CITIES\n";

    private const NONE = 4294967295;

    // Supports deliberately configured large offline catalogs. Normal request
    // memory still bounds their use; this is an integrity bound, not a promise
    // that every catalog fits the application's interactive memory limit.
    private const MAX_CACHE_BYTES = 536870912;

    private const EXCLUDED_FEATURE_CODES = ['PPLX', 'PPLQ', 'PPLH', 'PPLCH', 'PPLW'];

    private string $vectors = '';

    private string $labels = '';

    private string $offsets = '';

    private string $nodes = '';

    private int $nodeCount = 0;

    private array $roots = [];

    private array $regionNames = [];

    private int $globalRoot = self::NONE;

    private function __construct() {}

    public static function signature(string $path, ?string $adminPath = null): string
    {
        $cityHash = @hash_file('xxh128', $path);
        $adminHash = $adminPath === null ? '' : @hash_file('xxh128', $adminPath);

        if ($cityHash === false) {
            throw new RuntimeException("Could not read GeoNames city source [{$path}].");
        }

        if ($adminHash === false) {
            throw new RuntimeException("Could not read GeoNames region source [{$adminPath}].");
        }

        return hash('xxh128', self::VERSION.$path.$cityHash.$adminPath.$adminHash);
    }

    public static function fromFiles(string $path, ?string $adminPath = null, ?string $cacheDirectory = null, ?string $signature = null): self
    {
        $signature ??= self::signature($path, $adminPath);
        $cachePath = $cacheDirectory === null ? null : $cacheDirectory.DIRECTORY_SEPARATOR.$signature.'.bin';

        if ($cachePath !== null && ($cached = self::readCache($cachePath, $signature)) !== null) {
            return $cached;
        }

        $index = new self;
        $index->build($path, $adminPath);

        // An atomic replacement between the initial hash and opening the
        // streams must never publish different data under the old cache key.
        if (self::signature($path, $adminPath) !== $signature) {
            throw new RuntimeException('The GeoNames city or region source changed while its index was being built. Retry the lookup.');
        }

        if ($cachePath !== null) {
            $index->writeCache($cachePath, $signature);
        }

        return $index;
    }

    public function nearest(float $latitude, float $longitude, string|array|null $countryCode = null): ?GeocodedLocation
    {
        $countryCodes = is_string($countryCode) ? [$countryCode] : $countryCode;
        $roots = $countryCodes === null ? [$this->globalRoot] : array_map(fn (string $code): int => $this->roots[mb_strtoupper($code)] ?? self::NONE, $countryCodes);
        $roots = array_values(array_unique(array_filter($roots, fn (int $root): bool => $root !== self::NONE)));

        if ($roots === []) {
            return null;
        }

        $latitude = deg2rad($latitude);
        $longitude = deg2rad($longitude);
        $query = [cos($latitude) * cos($longitude), cos($latitude) * sin($longitude), sin($latitude)];
        $bestPoint = self::NONE;
        $bestDistance = INF;
        foreach ($roots as $root) {
            $this->search($root, $query, $bestPoint, $bestDistance);
        }
        $offset = unpack('V', $this->offsets, $bestPoint * 4)[1];
        // Avoid mb_strpos: even with an 8bit encoding it scans/converts the
        // complete multi-megabyte label table for every lookup.
        $end = $bestPoint + 1 < $this->count()
            ? unpack('V', $this->offsets, ($bestPoint + 1) * 4)[1] - 1
            : mb_strlen($this->labels, '8bit') - 1;
        [$city, $country, $geonamesId, $admin1Code] = explode("\t", mb_substr($this->labels, $offset, $end - $offset, '8bit'));
        $region = $this->regionNames[$country.'.'.$admin1Code] ?? '';
        $position = array_values(unpack('e3', $this->vectors, $bestPoint * 24));

        return new GeocodedLocation(
            null, $country, $city,
            region: $region === '' ? null : $region,
            source: 'geonames',
            nearestCityDistanceKm: 2 * 6371.0088 * asin(min(1.0, sqrt($bestDistance) / 2)),
            geonamesId: (int) $geonamesId,
            cityLatitude: rad2deg(asin(max(-1.0, min(1.0, $position[2])))),
            cityLongitude: rad2deg(atan2($position[1], $position[0])),
            cityAdmin1Code: $admin1Code === '' ? null : $admin1Code,
        );
    }

    public function count(): int
    {
        return intdiv(mb_strlen($this->vectors, '8bit'), 24);
    }

    private static function regions(string $path): array
    {
        $file = @fopen($path, 'rb');

        if ($file === false) {
            throw new RuntimeException("Could not read GeoNames region source [{$path}].");
        }

        $regions = [];
        $lineNumber = 0;

        try {
            while (($line = fgets($file)) !== false) {
                $lineNumber++;
                $columns = explode("\t", mb_rtrim($line, "\r\n"));

                if (count($columns) !== 4 || ! preg_match('/^[A-Z]{2}\.[^\s]+$/D', $columns[0]) || $columns[1] === '' || ! mb_check_encoding($columns[1], 'UTF-8') || mb_strlen($columns[1]) > 255 || str_contains($columns[1], "\0")) {
                    throw new RuntimeException("Invalid GeoNames region row [{$lineNumber}]: expected code, region name, ASCII name, and ID columns.");
                }

                $regions[$columns[0]] = $columns[1];
            }

            if (! feof($file)) {
                throw new RuntimeException("Could not finish reading GeoNames region source [{$path}].");
            }
        } finally {
            fclose($file);
        }

        return $regions;
    }

    private static function readCache(string $path, string $signature): ?self
    {
        if (! is_file($path)) {
            return null;
        }

        $file = @fopen($path, 'rb');

        if ($file === false) {
            return null;
        }

        try {
            if (fread($file, mb_strlen(self::MAGIC, '8bit')) !== self::MAGIC || mb_strlen($headerLength = fread($file, 4), '8bit') !== 4) {
                return null;
            }

            $headerLength = unpack('V', $headerLength)[1];

            if ($headerLength < 1 || $headerLength > 1048576) {
                return null;
            }

            $header = json_decode(fread($file, $headerLength), true);

            if (! is_array($header) || ($header['version'] ?? null) !== self::VERSION || ($header['signature'] ?? null) !== $signature || ! is_array($header['roots'] ?? null) || ! is_array($header['regionNames'] ?? null)) {
                return null;
            }

            foreach ($header['regionNames'] as $code => $name) {
                if (! is_string($code) || ! is_string($name) || mb_strlen($name) > 255) {
                    return null;
                }
            }

            $total = 0;

            foreach (['vectors', 'labels', 'offsets', 'nodes'] as $field) {
                if (! is_int($header[$field] ?? null) || $header[$field] < 1) {
                    return null;
                }

                $total += $header[$field];
            }

            if ($total > self::MAX_CACHE_BYTES || $header['vectors'] % 24 !== 0 || $header['offsets'] !== intdiv($header['vectors'], 24) * 4 || $header['nodes'] % 16 !== 0 || filesize($path) !== mb_strlen(self::MAGIC, '8bit') + 4 + $headerLength + $total) {
                return null;
            }

            $nodeCount = intdiv($header['nodes'], 16);

            foreach ([...array_values($header['roots']), $header['globalRoot'] ?? null] as $root) {
                if (! is_int($root) || $root < 0 || $root >= $nodeCount) {
                    return null;
                }
            }

            $index = new self;

            foreach (['vectors', 'labels', 'offsets', 'nodes'] as $field) {
                $index->{$field} = stream_get_contents($file, $header[$field]);

                if (mb_strlen($index->{$field}, '8bit') !== $header[$field]) {
                    return null;
                }
            }

            $index->roots = $header['roots'];
            $index->regionNames = $header['regionNames'];
            $index->globalRoot = $header['globalRoot'];

            return ($header['payload_hash'] ?? null) === $index->payloadHash() ? $index : null;
        } finally {
            fclose($file);
        }
    }

    private static function point(string $points, int $position): int
    {
        return unpack('V', $points, $position * 4)[1];
    }

    private function build(string $path, ?string $adminPath): void
    {
        $this->regionNames = $adminPath === null ? [] : self::regions($adminPath);
        $file = @fopen($path, 'rb');

        if ($file === false) {
            throw new RuntimeException("Could not read GeoNames city source [{$path}].");
        }

        $groups = [];
        $lineNumber = 0;

        try {
            while (($line = fgets($file)) !== false) {
                $lineNumber++;
                $line = mb_rtrim($line, "\r\n");

                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }

                $columns = explode("\t", $line);

                if (count($columns) !== 19 || ! ctype_digit($columns[0]) || (int) $columns[0] < 1 || ! is_numeric($columns[4]) || ! is_numeric($columns[5])) {
                    throw new RuntimeException("Invalid GeoNames city row [{$lineNumber}]: expected 19 tab-separated columns with a positive ID and numeric coordinates.");
                }

                $city = mb_trim($columns[1]);
                $country = mb_strtoupper(mb_trim($columns[8]));
                $latitude = (float) $columns[4];
                $longitude = (float) $columns[5];

                if ($city === '' || ! mb_check_encoding($city, 'UTF-8') || mb_strlen($city) > 255 || str_contains($city, "\0") || ! preg_match('/^[A-Z]{2}$/D', $country)) {
                    throw new RuntimeException("Invalid GeoNames city row [{$lineNumber}]: city names must be UTF-8 labels of at most 255 characters and country codes must contain two letters.");
                }

                if (! is_finite($latitude) || ! is_finite($longitude) || abs($latitude) > 90 || abs($longitude) > 180) {
                    throw new RuntimeException("Invalid GeoNames city row [{$lineNumber}]: coordinates must be finite WGS84 values.");
                }

                if ($columns[6] !== 'P' || $columns[7] === '') {
                    throw new RuntimeException("Invalid GeoNames city row [{$lineNumber}]: only populated place records are supported.");
                }

                // Apply the same settlement policy to raw replacements as to
                // the bundled normalized dataset: exclude neighbourhoods,
                // abandoned, historical, and destroyed populated places.
                if (in_array($columns[7], self::EXCLUDED_FEATURE_CODES, true)) {
                    continue;
                }

                $point = $this->count();
                $this->offsets .= pack('V', mb_strlen($this->labels, '8bit'));
                $this->labels .= $city."\t".$country."\t".$columns[0]."\t".$columns[10]."\n";
                $latitude = deg2rad($latitude);
                $longitude = deg2rad($longitude);
                $this->vectors .= pack('e3', cos($latitude) * cos($longitude), cos($latitude) * sin($longitude), sin($latitude));
                $groups[$country] ??= '';
                $groups[$country] .= pack('V', $point);
            }

            if (! feof($file)) {
                throw new RuntimeException("Could not finish reading GeoNames city source [{$path}].");
            }
        } finally {
            fclose($file);
        }

        if ($this->count() === 0) {
            throw new RuntimeException('The GeoNames city source contains no valid cities.');
        }

        // Every point appears in its country tree and the global tree. Reserve
        // their final binary storage to avoid a second large allocation while
        // repeatedly extending the node string during cold compilation.
        $this->nodes = str_repeat("\0", $this->count() * 32);

        foreach ($groups as $country => &$points) {
            $this->roots[$country] = $this->tree($points, 0, intdiv(mb_strlen($points, '8bit'), 4) - 1, 0);
            unset($groups[$country]);
        }

        unset($points, $groups);
        $points = '';

        for ($point = 0; $point < $this->count(); $point++) {
            $points .= pack('V', $point);
        }

        $this->globalRoot = $this->tree($points, 0, $this->count() - 1, 0);
    }

    /** Build a balanced tree using in-place quickselect, without sorting each level. */
    private function tree(string &$points, int $left, int $right, int $depth): int
    {
        if ($left > $right) {
            return self::NONE;
        }

        $axis = $depth % 3;
        $median = intdiv($left + $right, 2);
        $this->select($points, $left, $right, $median, $axis);
        $leftNode = $this->tree($points, $left, $median - 1, $depth + 1);
        $rightNode = $this->tree($points, $median + 1, $right, $depth + 1);
        $node = $this->nodeCount++;
        $packed = pack('V4', self::point($points, $median), $leftNode, $rightNode, $axis);

        for ($byte = 0; $byte < 16; $byte++) {
            $this->nodes[$node * 16 + $byte] = $packed[$byte];
        }

        return $node;
    }

    private function select(string &$points, int $left, int $right, int $median, int $axis): void
    {
        while ($left < $right) {
            $pivot = $this->coordinate(self::point($points, intdiv($left + $right, 2)), $axis);
            $lower = $left;
            $upper = $right;

            while ($lower <= $upper) {
                while ($this->coordinate(self::point($points, $lower), $axis) < $pivot) {
                    $lower++;
                }

                while ($this->coordinate(self::point($points, $upper), $axis) > $pivot) {
                    $upper--;
                }

                if ($lower <= $upper) {
                    for ($byte = 0; $byte < 4; $byte++) {
                        $temporary = $points[$lower * 4 + $byte];
                        $points[$lower * 4 + $byte] = $points[$upper * 4 + $byte];
                        $points[$upper * 4 + $byte] = $temporary;
                    }
                    $lower++;
                    $upper--;
                }
            }

            if ($median <= $upper) {
                $right = $upper;
            } elseif ($median >= $lower) {
                $left = $lower;
            } else {
                return;
            }
        }
    }

    private function coordinate(int $point, int $axis): float
    {
        return unpack('e', $this->vectors, $point * 24 + $axis * 8)[1];
    }

    private function search(int $node, array $query, int &$bestPoint, float &$bestDistance): void
    {
        if ($node === self::NONE) {
            return;
        }

        [$point, $left, $right, $axis] = array_values(unpack('V4', $this->nodes, $node * 16));
        $position = array_values(unpack('e3', $this->vectors, $point * 24));
        $distance = (float) (($position[0] - $query[0]) ** 2 + ($position[1] - $query[1]) ** 2 + ($position[2] - $query[2]) ** 2);

        if ($distance < $bestDistance || ($distance === $bestDistance && $point < $bestPoint)) {
            $bestPoint = $point;
            $bestDistance = $distance;
        }

        $difference = $query[$axis] - $position[$axis];
        $near = $difference < 0 ? $left : $right;
        $far = $difference < 0 ? $right : $left;
        $this->search($near, $query, $bestPoint, $bestDistance);

        if ($difference ** 2 <= $bestDistance) {
            $this->search($far, $query, $bestPoint, $bestDistance);
        }
    }

    private function writeCache(string $path, string $signature): void
    {
        $directory = dirname($path);

        if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            return;
        }

        $temporary = @tempnam($directory, 'cities-');

        if ($temporary === false) {
            return;
        }

        $file = @fopen($temporary, 'wb');

        if ($file === false) {
            @unlink($temporary);

            return;
        }

        $header = json_encode([
            'version' => self::VERSION,
            'signature' => $signature,
            'roots' => $this->roots,
            'regionNames' => $this->regionNames,
            'globalRoot' => $this->globalRoot,
            'vectors' => mb_strlen($this->vectors, '8bit'),
            'labels' => mb_strlen($this->labels, '8bit'),
            'offsets' => mb_strlen($this->offsets, '8bit'),
            'nodes' => mb_strlen($this->nodes, '8bit'),
            'payload_hash' => $this->payloadHash(),
        ], JSON_THROW_ON_ERROR);
        $prefix = self::MAGIC.pack('V', mb_strlen($header, '8bit')).$header;
        $written = fwrite($file, $prefix) === mb_strlen($prefix, '8bit');

        foreach (['vectors', 'labels', 'offsets', 'nodes'] as $field) {
            $written = $written && fwrite($file, $this->{$field}) === mb_strlen($this->{$field}, '8bit');
        }

        fclose($file);

        if (! $written || ! @rename($temporary, $path)) {
            @unlink($temporary);
        }
    }

    private function payloadHash(): string
    {
        $hash = hash_init('xxh128');

        foreach (['vectors', 'labels', 'offsets', 'nodes'] as $field) {
            hash_update($hash, $this->{$field});
        }

        hash_update($hash, serialize($this->regionNames));

        return hash_final($hash);
    }
}
