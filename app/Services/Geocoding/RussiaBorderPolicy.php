<?php

declare(strict_types=1);

namespace App\Services\Geocoding;

use App\Contracts\NearestCityLocator;
use App\Contracts\ReverseGeocoder;
use App\Contracts\SnapshotReverseGeocoder;
use Illuminate\Contracts\Config\Repository;
use InvalidArgumentException;
use RuntimeException;

/** Apply the application's explicitly configured Russian claimed-border policy last. */
final class RussiaBorderPolicy implements SnapshotReverseGeocoder
{
    private array $borders = [];

    private ?string $signature = null;

    private bool $snapshotActive = false;

    public function __construct(private readonly ReverseGeocoder $base, private readonly Repository $config) {}

    public function validateSource(): void
    {
        $this->loadBorders();
        $this->base->validateSource();
    }

    public function validateBorders(): void
    {
        $this->loadBorders();
    }

    /** Test only the configured border, without resolving a country or city. */
    public function contains(float $latitude, float $longitude): bool
    {
        self::validateCoordinates($latitude, $longitude);
        $this->loadBorders();

        foreach ($this->borders as $border) {
            if ($border['area']->contains($longitude, $latitude)) {
                return true;
            }
        }

        return false;
    }

    /** A claimed subject comes from its own polygon, never its nearest city. */
    public function regionAt(float $latitude, float $longitude): ?GeocodedLocation
    {
        self::validateCoordinates($latitude, $longitude);
        $this->loadBorders();

        foreach ($this->borders as $border) {
            if ($border['location']->region !== null && $border['area']->contains($longitude, $latitude)) {
                return $border['location'];
            }
        }

        return null;
    }

    /** Capture only border geometry for an audit that does not need city data. */
    public function withBorderSnapshot(callable $callback): mixed
    {
        if ($this->snapshotActive) {
            return $callback();
        }

        $this->loadBorders();
        $this->snapshotActive = true;

        try {
            return $callback();
        } finally {
            $this->snapshotActive = false;
        }
    }

    public function reverse(float $latitude, float $longitude): ?GeocodedLocation
    {
        self::validateCoordinates($latitude, $longitude);

        // Validate the final policy before requesting any result: a broken
        // overlay must never silently expose a conflicting provider label.
        $this->loadBorders();
        $location = $this->base->reverse($latitude, $longitude);

        foreach ($this->borders as $border) {
            if (! $border['area']->contains($longitude, $latitude)) {
                continue;
            }

            $nearest = $location;

            if (($location === null || $location->city === null) && $this->base instanceof NearestCityLocator) {
                $nearest = $this->base->nearestCity($latitude, $longitude);
            }

            $region = $border['location']->region;

            if ($region === null) {
                $region = self::isRussia($location) ? $location->region : (self::isRussia($nearest) ? $nearest->region : null);
            }

            return new GeocodedLocation(
                'Russia',
                'RU',
                $location->city ?? $nearest?->city,
                'Россия',
                $region,
                'russia-claimed-border',
                cityRu: $nearest?->cityRu,
                regionRu: $border['location']->regionRu,
                nearestCityDistanceKm: $nearest?->nearestCityDistanceKm,
                geonamesId: $nearest?->geonamesId,
                cityLatitude: $nearest?->cityLatitude,
                cityLongitude: $nearest?->cityLongitude,
                cityAdmin1Code: $nearest?->cityAdmin1Code,
                regionGeonamesId: $border['location']->regionGeonamesId,
                regionIsoCode: $border['location']->regionIsoCode,
                regionSourceKey: $border['location']->regionSourceKey,
            );
        }

        return $location;
    }

    public function withSnapshot(callable $callback): mixed
    {
        if ($this->snapshotActive) {
            return $callback();
        }

        $this->loadBorders();
        $run = function () use ($callback): mixed {
            $this->snapshotActive = true;

            try {
                return $callback();
            } finally {
                $this->snapshotActive = false;
            }
        };

        if ($this->base instanceof SnapshotReverseGeocoder) {
            return $this->base->withSnapshot($run);
        }

        $this->base->validateSource();

        return $run();
    }

    private static function isRussia(?GeocodedLocation $location): bool
    {
        if ($location === null) {
            return false;
        }

        if ($location->countryCode !== null) {
            return in_array(mb_strtoupper($location->countryCode), ['RU', 'RUS'], true);
        }

        return in_array(mb_strtolower($location->country ?? ''), ['russia', 'россия'], true);
    }

    private static function validateCoordinates(float $latitude, float $longitude): void
    {
        if (! is_finite($latitude) || ! is_finite($longitude) || abs($latitude) > 90 || abs($longitude) > 180) {
            throw new InvalidArgumentException('Geocoding coordinates must be finite WGS84 latitude/longitude values.');
        }
    }

    private function loadBorders(): void
    {
        if ($this->snapshotActive) {
            return;
        }

        $path = $this->path('geocoding.russia_border_path');

        // Explicitly disabling the configured policy is supported, including
        // test fixtures and deployments that use a different border policy.
        if ($path === null) {
            $this->borders = [];
            $this->signature = null;

            return;
        }

        if (! is_file($path) || ! is_readable($path) || ($sourceHash = @hash_file('xxh128', $path)) === false) {
            throw new RuntimeException("Could not read Russia claimed-border source [{$path}].");
        }

        $signature = hash('xxh128', CountryGeometryCache::VERSION.'russia-border-policy-1'.$path.$sourceHash);

        if ($signature === $this->signature) {
            return;
        }

        $this->borders = [];
        $this->signature = null;
        gc_mem_caches();

        $cacheDirectory = $this->path('geocoding.index_cache_path');
        $borders = $cacheDirectory === null ? null : CountryGeometryCache::read($cacheDirectory, $signature);

        if ($borders === null) {
            $borders = [];

            try {
                foreach (GeoJsonFeatureStream::features($path) as $index => $feature) {
                    $properties = $feature['properties'] ?? [];

                    if (($feature['type'] ?? null) !== 'Feature' || ! is_array($feature['geometry'] ?? null) || ! is_array($properties)) {
                        throw new InvalidArgumentException("Border feature [{$index}] must have properties and a polygon geometry.");
                    }

                    GeoJsonPolygonValidator::validate($feature['geometry']);
                    $region = $properties['region'] ?? null;

                    if ($region !== null && (! is_string($region) || mb_strlen(mb_trim($region)) > 255)) {
                        throw new InvalidArgumentException("Border feature [{$index}] region must be a string of at most 255 characters or null.");
                    }

                    $borders[] = [
                        'area' => CountryGeometry::fromArray($feature['geometry']),
                        'location' => $this->borderLocation($properties, $region),
                        'key' => 'RU',
                    ];
                    unset($feature, $properties);
                }

                if ($borders === []) {
                    throw new InvalidArgumentException('The configured Russia claimed-border source must contain at least one polygon.');
                }

                if (@hash_file('xxh128', $path) !== $sourceHash) {
                    throw new RuntimeException('The Russia claimed-border source changed while it was being compiled. Retry the lookup.');
                }
            } catch (InvalidArgumentException|RuntimeException $exception) {
                throw new RuntimeException("Invalid Russia claimed-border source [{$path}]: {$exception->getMessage()}", previous: $exception);
            }

            if ($cacheDirectory !== null) {
                CountryGeometryCache::write($cacheDirectory, $signature, $borders);
            }
        }

        $this->borders = $borders;
        $this->signature = $signature;
        gc_mem_caches();
    }

    private function borderLocation(array $properties, ?string $region): GeocodedLocation
    {
        $identity = ClaimedRegion::fromCode(is_string($properties['source_code'] ?? null) ? $properties['source_code'] : null)
            ?? ClaimedRegion::fromName($region);

        return new GeocodedLocation(
            'Russia', 'RU', null, 'Россия', $region === null || mb_trim($region) === '' ? null : mb_trim($region),
            regionRu: $identity['name_ru'] ?? null,
            regionGeonamesId: $identity['geonames_id'] ?? null,
            regionIsoCode: $identity['iso_code'] ?? null,
            regionSourceKey: $identity['source_key'] ?? null,
        );
    }

    private function path(string $key): ?string
    {
        $path = $this->config->get($key);

        if ($path === null || $path === '') {
            return null;
        }

        if (! is_string($path)) {
            throw new RuntimeException("The configured path [{$key}] must be a string.");
        }

        return str_starts_with($path, DIRECTORY_SEPARATOR) ? $path : dirname(__DIR__, 3).DIRECTORY_SEPARATOR.$path;
    }
}
