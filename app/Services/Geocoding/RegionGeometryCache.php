<?php

declare(strict_types=1);

namespace App\Services\Geocoding;

use RuntimeException;

/** Country-partitioned geometry; compilation retains one feature at a time. */
final class RegionGeometryCache
{
    private const MAGIC = "RODNIK-REGIONS-1\n";

    public static function ready(string $directory, string $signature): bool
    {
        $marker = self::marker($directory, $signature);

        return ($marker['signature'] ?? null) === $signature && is_array($marker['countries'] ?? null);
    }

    public static function build(string $directory, string $signature, callable $entries): void
    {
        if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('Could not create the administrative geometry cache directory.');
        }

        $temporary = $directory.'/regions-building-'.bin2hex(random_bytes(8));

        if (! mkdir($temporary, 0775)) {
            throw new RuntimeException('Could not create temporary administrative geometry caches.');
        }

        $countries = [];

        try {
            foreach ($entries() as $entry) {
                $country = $entry['key'];
                $path = $temporary.'/'.$country.'.bin';
                $file = fopen($path, isset($countries[$country]) ? 'ab' : 'wb');

                if ($file === false) {
                    throw new RuntimeException('Could not write administrative geometry cache.');
                }

                try {
                    if (! isset($countries[$country])) {
                        self::write($file, self::MAGIC.$signature."\n");
                        $countries[$country] = true;
                    }

                    $payload = serialize($entry);
                    self::write($file, pack('V', mb_strlen($payload, '8bit')).hash('xxh128', $payload).$payload);
                } finally {
                    fclose($file);
                }
            }

            // The generator validates the complete source and verifies its
            // fingerprint before any partition or marker is published.
            foreach (array_keys($countries) as $country) {
                $file = fopen($temporary.'/'.$country.'.bin', 'ab');

                if ($file === false) {
                    throw new RuntimeException('Could not finish administrative geometry cache.');
                }

                try {
                    self::write($file, pack('V', 0));
                } finally {
                    fclose($file);
                }

                if (! rename($temporary.'/'.$country.'.bin', self::path($directory, $signature, $country))) {
                    throw new RuntimeException('Could not publish administrative geometry cache.');
                }
            }

            file_put_contents($temporary.'/ready', json_encode(['signature' => $signature, 'countries' => array_keys($countries)], JSON_THROW_ON_ERROR));

            if (! rename($temporary.'/ready', $directory.'/regions-'.$signature.'.ready')) {
                throw new RuntimeException('Could not publish the administrative geometry cache marker.');
            }
        } finally {
            foreach (glob($temporary.'/*') ?: [] as $path) {
                unlink($path);
            }

            rmdir($temporary);
        }
    }

    public static function read(string $directory, string $signature, string $country): ?array
    {
        $path = self::path($directory, $signature, $country);

        if (! is_file($path)) {
            return in_array($country, self::marker($directory, $signature)['countries'] ?? [], true) ? null : [];
        }

        $file = @fopen($path, 'rb');

        if ($file === false) {
            return null;
        }

        try {
            $header = self::MAGIC.$signature."\n";

            if (fread($file, mb_strlen($header, '8bit')) !== $header) {
                return null;
            }

            $regions = [];

            while (! feof($file)) {
                $length = fread($file, 4);

                if (mb_strlen($length, '8bit') !== 4) {
                    return null;
                }

                $length = unpack('V', $length)[1];

                if ($length === 0) {
                    return fread($file, 1) === '' ? $regions : null;
                }

                if ($length > 67108864) {
                    return null;
                }

                $hash = fread($file, 32);
                $payload = stream_get_contents($file, $length);

                if (mb_strlen($hash, '8bit') !== 32 || mb_strlen($payload, '8bit') !== $length || ! hash_equals($hash, hash('xxh128', $payload))) {
                    return null;
                }

                $entry = @unserialize($payload, ['allowed_classes' => [CountryGeometry::class, GeocodedLocation::class]]);

                if (! is_array($entry) || ! ($entry['area'] ?? null) instanceof CountryGeometry || ! ($entry['location'] ?? null) instanceof GeocodedLocation || ($entry['key'] ?? null) !== $country) {
                    return null;
                }

                $regions[] = $entry;
            }

            return null;
        } finally {
            fclose($file);
        }
    }

    private static function path(string $directory, string $signature, string $country): string
    {
        return $directory.'/regions-'.$signature.'-'.$country.'.bin';
    }

    private static function marker(string $directory, string $signature): array
    {
        $path = $directory.'/regions-'.$signature.'.ready';

        if (! is_file($path)) {
            return [];
        }

        $content = @file_get_contents($path);

        if (! is_string($content)) {
            return [];
        }

        $marker = json_decode($content, true);

        return is_array($marker) ? $marker : [];
    }

    private static function write(mixed $stream, string $data): void
    {
        if (fwrite($stream, $data) !== mb_strlen($data, '8bit')) {
            throw new RuntimeException('Could not finish administrative geometry cache.');
        }
    }
}
