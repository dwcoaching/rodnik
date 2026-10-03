<?php

declare(strict_types=1);

namespace App\Services\Geocoding;

use Illuminate\Contracts\Config\Repository;
use InvalidArgumentException;
use RuntimeException;

/** Actual administrative containment, with one country's geometry in memory. */
final class RegionBoundaryIndex
{
    private array $regions = [];

    private ?string $signature = null;

    private ?string $loadedCountry = null;

    private ?string $sourcePath = null;

    private ?string $sourceHash = null;

    private bool $snapshotActive = false;

    public function __construct(private readonly Repository $config, private readonly ?RussiaBorderPolicy $policy = null) {}

    public function validateSource(): void
    {
        $this->prepare();
    }

    public function withSnapshot(callable $callback): mixed
    {
        if ($this->snapshotActive) {
            return $callback();
        }

        $this->prepare();
        $this->snapshotActive = true;

        try {
            return $callback();
        } finally {
            $this->snapshotActive = false;
        }
    }

    public function region(float $latitude, float $longitude, ?string $countryCode = null): ?GeocodedLocation
    {
        if (! is_finite($latitude) || ! is_finite($longitude) || abs($latitude) > 90 || abs($longitude) > 180) {
            throw new InvalidArgumentException('Region coordinates must be finite WGS84 values.');
        }

        if ($countryCode !== null && ! preg_match('/^[A-Z]{2,3}$/D', $countryCode)) {
            throw new InvalidArgumentException('Region country filters must be uppercase country codes.');
        }

        $this->prepare();

        if ($countryCode === 'RU' && ($claimed = $this->policy?->regionAt($latitude, $longitude)) !== null) {
            return $claimed;
        }

        $this->loadCountry($countryCode);

        foreach ($this->regions as $region) {
            if ($region['area']->contains($longitude, $latitude)) {
                return $region['location'];
            }
        }

        return null;
    }

    private function prepare(): void
    {
        if ($this->snapshotActive) {
            return;
        }

        $path = $this->config->get('geocoding.regions_path');

        if ($path === null || $path === '') {
            $this->regions = [];
            $this->signature = $this->sourcePath = $this->sourceHash = $this->loadedCountry = null;

            return;
        }

        if (! is_string($path)) {
            throw new RuntimeException('The region geometry path must be a string.');
        }

        $path = str_starts_with($path, DIRECTORY_SEPARATOR) ? $path : dirname(__DIR__, 3).DIRECTORY_SEPARATOR.$path;
        $hash = @hash_file('xxh128', $path);

        if ($hash === false) {
            throw new RuntimeException("Could not read region geography [{$path}].");
        }

        $signature = hash('xxh128', CountryGeometryCache::VERSION.'regions-partitioned-2'.$path.$hash);

        if ($signature === $this->signature) {
            return;
        }

        $this->regions = [];
        $this->signature = $this->loadedCountry = null;
        $this->sourcePath = $path;
        $this->sourceHash = $hash;
        gc_mem_caches();
        $directory = $this->cacheDirectory();

        if ($directory !== null) {
            if (! RegionGeometryCache::ready($directory, $signature)) {
                RegionGeometryCache::build($directory, $signature, fn (): iterable => $this->entries());
            }
        } else {
            // Without caches validation still covers the entire replacement,
            // without retaining a worldwide geometry array.
            foreach ($this->entries() as $entry) {
                unset($entry);
            }
        }

        $this->signature = $signature;
        gc_mem_caches();
    }

    private function loadCountry(?string $country): void
    {
        $key = $country ?? '*';

        if ($this->sourcePath === null || $this->loadedCountry === $key) {
            return;
        }

        $this->regions = [];
        $this->loadedCountry = null;
        gc_mem_caches();
        $directory = $this->cacheDirectory();
        $regions = $directory !== null && $country !== null ? RegionGeometryCache::read($directory, $this->signature, $country) : null;

        if ($regions === null) {
            if ($directory !== null && $country !== null) {
                RegionGeometryCache::build($directory, $this->signature, fn (): iterable => $this->entries());
                $regions = RegionGeometryCache::read($directory, $this->signature, $country);
            } else {
                $regions = [];

                foreach ($this->entries() as $entry) {
                    if ($country === null || $entry['key'] === $country) {
                        $regions[] = $entry;
                    }
                }
            }
        }

        if ($regions === null) {
            throw new RuntimeException('Could not read the rebuilt region geometry cache.');
        }

        $this->regions = $regions;
        $this->loadedCountry = $key;
        gc_mem_caches();
    }

    private function entries(): iterable
    {
        $count = 0;

        foreach (GeoJsonFeatureStream::features($this->sourcePath) as $feature) {
            $properties = $feature['properties'] ?? [];
            GeoJsonPolygonValidator::validate($feature['geometry'] ?? []);

            foreach (['source_key', 'country_code', 'name'] as $field) {
                if (! is_string($properties[$field] ?? null) || mb_trim($properties[$field]) === '' || mb_strlen($properties[$field]) > 255 || ! mb_check_encoding($properties[$field], 'UTF-8') || str_contains($properties[$field], "\0")) {
                    throw new RuntimeException("Region polygons require a valid [{$field}].");
                }
            }

            if (! preg_match('/^[A-Z]{2}$/D', $properties['country_code'])) {
                throw new RuntimeException('Region country codes must contain two uppercase ASCII letters.');
            }

            foreach (['name_ru', 'iso_code'] as $field) {
                if (($properties[$field] ?? null) !== null && (! is_string($properties[$field]) || mb_strlen($properties[$field]) > 255 || ! mb_check_encoding($properties[$field], 'UTF-8') || str_contains($properties[$field], "\0"))) {
                    throw new RuntimeException("Region property [{$field}] must be a valid string or null.");
                }
            }

            $id = $properties['geonames_id'] ?? null;

            if ($id !== null && (! is_int($id) || $id < 1)) {
                throw new RuntimeException('Region GeoNames IDs must be positive integers or null.');
            }

            $count++;
            $country = $properties['country_code'];
            yield [
                'area' => CountryGeometry::fromArray($feature['geometry']),
                'location' => new GeocodedLocation(
                    null, $country, null,
                    region: $properties['name'],
                    source: 'region-geojson',
                    regionRu: $properties['name_ru'] ?? null,
                    regionGeonamesId: $id,
                    regionIsoCode: $properties['iso_code'] ?? null,
                    regionSourceKey: $properties['source_key'],
                ),
                'key' => $country,
            ];
            unset($feature, $properties);
        }

        if ($count === 0) {
            throw new RuntimeException('The region source must contain at least one polygon.');
        }

        if (@hash_file('xxh128', $this->sourcePath) !== $this->sourceHash) {
            throw new RuntimeException('The region geography changed while its index was being built. Retry the lookup.');
        }
    }

    private function cacheDirectory(): ?string
    {
        $directory = $this->config->get('geocoding.index_cache_path');

        return is_string($directory) && $directory !== '' ? $directory : null;
    }
}
