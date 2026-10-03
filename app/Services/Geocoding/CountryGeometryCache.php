<?php

declare(strict_types=1);

namespace App\Services\Geocoding;

/** A disposable compiled-country cache; the GeoJSON remains authoritative. */
final class CountryGeometryCache
{
    public const VERSION = 4;

    private const MAGIC = "RODNIK-COUNTRIES-1\n";

    public static function read(string $directory, string $signature): ?array
    {
        $path = $directory.DIRECTORY_SEPARATOR.'countries-'.$signature.'.bin';

        if (! is_file($path) || filesize($path) > 67108864) {
            return null;
        }

        $file = @fopen($path, 'rb');
        $prefixLength = mb_strlen(self::MAGIC, '8bit');

        if ($file === false) {
            return null;
        }

        try {
            if (fread($file, $prefixLength) !== self::MAGIC) {
                return null;
            }

            $hash = fread($file, 32);

            if (mb_strlen($hash, '8bit') !== 32 || fread($file, 1) !== "\n") {
                return null;
            }

            // Read only the serialized payload. Retaining a complete cache
            // file as well doubles the large packed geometry allocation.
            $payload = stream_get_contents($file);
        } finally {
            fclose($file);
        }

        if ($payload === false || $payload === '' || ! hash_equals($hash, hash('xxh128', $payload))) {
            return null;
        }

        $countries = @unserialize($payload, ['allowed_classes' => [CountryGeometry::class, GeocodedLocation::class]]);
        unset($payload);

        if (! is_array($countries) || $countries === [] || ! array_is_list($countries)) {
            return null;
        }

        foreach ($countries as $country) {
            if (! is_array($country) || ! ($country['area'] ?? null) instanceof CountryGeometry || ! ($country['location'] ?? null) instanceof GeocodedLocation || ! is_string($country['key'] ?? null)) {
                return null;
            }
        }

        return $countries;
    }

    public static function write(string $directory, string $signature, array $countries): void
    {
        if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            return;
        }

        $path = $directory.DIRECTORY_SEPARATOR.'countries-'.$signature.'.bin';
        $temporary = @tempnam($directory, 'countries-');

        if ($temporary === false) {
            return;
        }

        $payload = serialize($countries);
        $file = @fopen($temporary, 'wb');

        if ($file === false) {
            @unlink($temporary);

            return;
        }

        $header = self::MAGIC.hash('xxh128', $payload)."\n";
        $written = fwrite($file, $header) === mb_strlen($header, '8bit')
            && fwrite($file, $payload) === mb_strlen($payload, '8bit');
        fclose($file);

        if (! $written || ! @rename($temporary, $path)) {
            @unlink($temporary);
        }
    }
}
