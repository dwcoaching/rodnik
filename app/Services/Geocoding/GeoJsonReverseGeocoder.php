<?php

declare(strict_types=1);

namespace App\Services\Geocoding;

use App\Contracts\NearestCityLocator;
use App\Contracts\SnapshotReverseGeocoder;
use Illuminate\Contracts\Config\Repository;
use InvalidArgumentException;
use JsonException;
use RuntimeException;
use Traversable;

/**
 * Offline country containment and nearest city lookup. The supplied spring
 * export is an exact-coordinate cache; its points are not city coordinates.
 */
final class GeoJsonReverseGeocoder implements NearestCityLocator, SnapshotReverseGeocoder
{
    private ?string $signature = null;

    private array $references = [];

    private array $countries = [];

    private array $cities = [];

    private array $citiesByCountry = [];

    private array $fallbackReferences = [];

    private ?string $referenceSignature = null;

    private ?CitySpatialIndex $externalCities = null;

    private ?string $citySignature = null;

    private bool $snapshotActive = false;

    public function __construct(private readonly Repository $config) {}

    public function validateSource(): void
    {
        $this->load();
    }

    public function withSnapshot(callable $callback): mixed
    {
        if ($this->snapshotActive) {
            return $callback();
        }

        $this->load();
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
        $this->load();

        $key = self::coordinateKey($latitude, $longitude);

        if (isset($this->references[$key])) {
            return $this->references[$key]['location'];
        }

        foreach ($this->countries as $country) {
            if (! $country['area']->contains($longitude, $latitude)) {
                continue;
            }

            $location = $country['location'];
            $scope = $this->cityScope();
            $nearest = $this->nearest($scope === 'global' ? $this->cities : ($this->citiesByCountry[$country['key']] ?? []), $latitude, $longitude);
            $city = $this->externalCities?->nearest($latitude, $longitude, $scope === 'country' ? $this->cityCountryCodes($location) : null)
                ?? $this->pointLocation($nearest);

            if ($city === null && isset($this->fallbackReferences[$key])) {
                $reference = $this->fallbackReferences[$key];

                if (($location->countryCode !== null && $reference->countryCode === $location->countryCode)
                    || ($location->country !== null && $reference->country === $location->country)) {
                    $city = $reference;
                }
            }

            return new GeocodedLocation(
                $location->country,
                $location->countryCode,
                $city?->city,
                $location->countryRu,
                $this->sameCountry($location, $city) ? ($city->region ?? $location->region) : $location->region,
                $this->externalCities === null ? 'geojson' : 'geojson-geonames',
                cityRu: $city?->cityRu,
                nearestCityDistanceKm: $city?->nearestCityDistanceKm,
                geonamesId: $city?->geonamesId,
                cityLatitude: $city?->cityLatitude,
                cityLongitude: $city?->cityLongitude,
                cityAdmin1Code: $city?->cityAdmin1Code,
            );
        }

        // Without boundaries, city records must carry their own country labels.
        if ($this->countries === [] && $this->cities !== []) {
            return $this->pointLocation($this->nearest($this->cities, $latitude, $longitude));
        }

        // Real country/city geography is authoritative. The original export
        // supplies known exact labels only where that geography has no match.
        if (isset($this->fallbackReferences[$key])) {
            return $this->fallbackReferences[$key];
        }

        $maximum = (float) $this->config->get('geocoding.reference_max_distance_km', 0);

        if ($maximum > 0 && $this->references !== []) {
            $nearest = $this->nearest($this->references, $latitude, $longitude);

            if ($nearest['distance'] <= $maximum) {
                $location = $nearest['point']['location'];

                return new GeocodedLocation(
                    $location->country,
                    $location->countryCode,
                    $location->city,
                    $location->countryRu,
                    $location->region,
                    'geojson-nearby-reference',
                );
            }
        }

        return null;
    }

    /** Find the city independently when a final border policy supplies the country. */
    public function nearestCity(float $latitude, float $longitude): ?GeocodedLocation
    {
        self::validateCoordinates($latitude, $longitude);
        $this->load();

        return $this->externalCities?->nearest($latitude, $longitude)
            ?? $this->pointLocation($this->nearest($this->cities, $latitude, $longitude));
    }

    private static function validateCoordinates(float $latitude, float $longitude): void
    {
        if (! is_finite($latitude) || ! is_finite($longitude) || abs($latitude) > 90 || abs($longitude) > 180) {
            throw new InvalidArgumentException('Geocoding coordinates must be finite WGS84 latitude/longitude values.');
        }
    }

    private static function firstCharacter(string $path): ?string
    {
        $file = fopen($path, 'rb');

        if ($file === false) {
            throw new RuntimeException("Could not read geocoding source [{$path}].");
        }

        try {
            while (($chunk = fread($file, 4096)) !== '') {
                if ($chunk === false) {
                    throw new RuntimeException("Could not finish reading geocoding source [{$path}].");
                }

                $chunk = mb_ltrim($chunk);

                if ($chunk !== '') {
                    return $chunk[0];
                }
            }

            return null;
        } finally {
            fclose($file);
        }
    }

    private static function position(mixed $position): array
    {
        if (! is_array($position) || ! array_is_list($position) || count($position) < 2 || ! is_numeric($position[0]) || ! is_numeric($position[1])) {
            throw new InvalidArgumentException('GeoJSON positions must contain numeric longitude and latitude.');
        }

        $longitude = (float) $position[0];
        $latitude = (float) $position[1];
        self::validateCoordinates($latitude, $longitude);

        return [$longitude, $latitude];
    }

    private static function validatePolygon(array $geometry): void
    {
        GeoJsonPolygonValidator::validate($geometry);
    }

    private static function coordinateKey(float $latitude, float $longitude): string
    {
        $latitude = round($latitude, 6);
        $longitude = round($longitude, 6);

        return sprintf('%.6F,%.6F', $latitude === 0.0 ? 0.0 : $latitude, $longitude === 0.0 ? 0.0 : $longitude);
    }

    private static function countryKey(GeocodedLocation $location): string
    {
        return $location->countryCode ?? mb_strtolower($location->country ?? '');
    }

    private static function point(float $latitude, float $longitude, GeocodedLocation $location): array
    {
        return [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'latitudeRadians' => deg2rad($latitude),
            'longitudeRadians' => deg2rad($longitude),
            'cosLatitude' => cos(deg2rad($latitude)),
            'location' => $location,
        ];
    }

    private function load(): void
    {
        if ($this->snapshotActive) {
            return;
        }

        $this->cityScope();

        $path = $this->config->get('geocoding.path');

        if (! is_string($path) || mb_trim($path) === '') {
            throw new RuntimeException('A geocoding source path must be configured.');
        }

        if (! str_starts_with($path, DIRECTORY_SEPARATOR)) {
            $path = dirname(__DIR__, 3).DIRECTORY_SEPARATOR.$path;
        }

        $sourceHash = @hash_file('xxh128', $path);

        if ($sourceHash === false) {
            throw new RuntimeException("Could not read geocoding source [{$path}].");
        }

        $maximum = $this->config->get('geocoding.reference_max_distance_km', 0);

        if (! is_numeric($maximum) || ! is_finite((float) $maximum) || (float) $maximum < 0) {
            throw new RuntimeException('The geocoding reference maximum distance must be a finite non-negative number.');
        }

        // PHP stat timestamps have one-second precision. Hashing the contents
        // also catches same-size replacements within one second in workers.
        // xxh128 keeps this inexpensive even during a complete recalculation.
        $signature = hash('xxh128', CountryGeometryCache::VERSION.$path.$sourceHash.serialize($this->config->get('geocoding.properties', [])));

        if ($signature !== $this->signature) {
            // Do not hold complete old and new country/city indexes at once.
            // Snapshots skip this branch until the current chunk has ended.
            $this->signature = null;
            $this->references = [];
            $this->countries = [];
            $this->cities = [];
            $this->citiesByCountry = [];
            $this->externalCities = null;
            $this->citySignature = null;
            $this->fallbackReferences = [];
            $this->referenceSignature = null;
            gc_mem_caches();

            try {
                $first = self::firstCharacter($path);
                $cacheDirectory = $this->optionalPath('geocoding.index_cache_path');
                $cachedCountries = $first === '{' && $cacheDirectory !== null ? CountryGeometryCache::read($cacheDirectory, $signature) : null;

                if ($cachedCountries !== null) {
                    [$references, $countries, $cities, $citiesByCountry] = [[], $cachedCountries, [], []];
                } else {
                    $data = $first === '[' ? GeoJsonFeatureStream::records($path) : GeoJsonFeatureStream::features($path);
                    [$references, $countries, $cities, $citiesByCountry] = $this->compile($data, flat: $first === '[');

                    if (@hash_file('xxh128', $path) !== $sourceHash) {
                        throw new RuntimeException('The geocoding source changed while it was being compiled. Retry the lookup.');
                    }

                    if ($cacheDirectory !== null && $references === [] && $cities === [] && $countries !== []) {
                        CountryGeometryCache::write($cacheDirectory, $signature, $countries);
                    }
                }
            } catch (JsonException|InvalidArgumentException|RuntimeException $exception) {
                throw new RuntimeException("Invalid geocoding source [{$path}]: {$exception->getMessage()}", previous: $exception);
            }

            // Commit the new data only after the whole replacement is validated.
            $this->references = $references;
            $this->countries = $countries;
            $this->cities = $cities;
            $this->citiesByCountry = $citiesByCountry;
            $this->signature = $signature;

            // Streaming compilation creates many temporary coordinate arrays.
            // Release their allocator pages before building the city index,
            // including when a long-running worker replaces both datasets.
            unset($data, $cachedCountries);
            gc_mem_caches();
        }

        $this->loadCities();
        $this->loadReferences();

        if ($this->cityScope() === 'country') {
            foreach ($this->countries as $country) {
                $this->cityCountryCodes($country['location']);
            }
        }
    }

    private function loadCities(): void
    {
        $path = $this->optionalPath('geocoding.cities_path');

        // A replacement FeatureCollection with city points is self-contained.
        if ($path === null || $this->cities !== []) {
            $this->externalCities = null;
            $this->citySignature = null;

            return;
        }

        $adminPath = $this->optionalPath('geocoding.admin1_path');
        $signature = CitySpatialIndex::signature($path, $adminPath);

        if ($signature !== $this->citySignature) {
            $this->externalCities = null;
            $this->citySignature = null;
            gc_mem_caches();
            $cacheDirectory = $this->optionalPath('geocoding.index_cache_path');
            $this->externalCities = CitySpatialIndex::fromFiles($path, $adminPath, $cacheDirectory, $signature);
            $this->citySignature = $signature;
        }
    }

    private function loadReferences(): void
    {
        $path = $this->optionalPath('geocoding.reference_path');

        if ($path === null) {
            $this->fallbackReferences = [];
            $this->referenceSignature = null;

            return;
        }

        $sourceHash = @hash_file('xxh128', $path);

        if ($sourceHash === false) {
            throw new RuntimeException("Could not read geocoding reference source [{$path}].");
        }

        $signature = hash('xxh128', $path.$sourceHash.serialize($this->config->get('geocoding.reference_properties', [])));

        if ($signature === $this->referenceSignature) {
            return;
        }

        $this->fallbackReferences = [];
        $this->referenceSignature = null;

        try {
            [$references] = $this->compile(GeoJsonFeatureStream::records($path), reference: true, flat: true);

            if (@hash_file('xxh128', $path) !== $sourceHash) {
                throw new RuntimeException('The geocoding reference source changed while it was being compiled. Retry the lookup.');
            }
        } catch (JsonException|InvalidArgumentException|RuntimeException $exception) {
            throw new RuntimeException("Invalid geocoding reference source [{$path}]: {$exception->getMessage()}", previous: $exception);
        }

        $this->fallbackReferences = $references;
        $this->referenceSignature = $signature;
    }

    private function optionalPath(string $key): ?string
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

    private function cityCountryCodes(GeocodedLocation $location): ?array
    {
        if ($location->countryCode === null) {
            return null;
        }

        $aliases = $this->config->get('geocoding.city_country_aliases.'.$location->countryCode, []);

        if (! is_array($aliases) || ! array_is_list($aliases) || array_filter($aliases, fn ($code) => ! is_string($code) || ! preg_match('/^[A-Z]{2,3}$/D', $code)) !== []) {
            throw new RuntimeException('Geocoding city country aliases must be lists of uppercase country codes.');
        }

        return [$location->countryCode, ...$aliases];
    }

    private function cityScope(): string
    {
        $scope = $this->config->get('geocoding.city_scope', 'global');

        if (! in_array($scope, ['global', 'country'], true)) {
            throw new RuntimeException('The geocoding city scope must be global or country.');
        }

        return $scope;
    }

    private function sameCountry(GeocodedLocation $country, ?GeocodedLocation $city): bool
    {
        if ($city === null) {
            return false;
        }

        if ($country->countryCode !== null && $city->countryCode !== null) {
            return $country->countryCode === $city->countryCode;
        }

        return $country->country !== null && $city->country !== null && strcasecmp($country->country, $city->country) === 0;
    }

    private function compile(mixed $data, bool $reference = false, bool $flat = false): array
    {
        $streaming = $data instanceof Traversable;

        if (! $streaming && (! is_array($data) || $data === [])) {
            throw new InvalidArgumentException('Expected a non-empty spring export or GeoJSON FeatureCollection.');
        }

        $references = [];
        $countries = [];
        $cities = [];

        if ($flat || (! $streaming && array_is_list($data))) {
            foreach ($data as $index => $row) {
                if (! is_array($row) || ! isset($row['latitude'], $row['longitude']) || ! is_numeric($row['latitude']) || ! is_numeric($row['longitude'])) {
                    throw new InvalidArgumentException("Spring reference [{$index}] must contain numeric latitude and longitude.");
                }

                $latitude = (float) $row['latitude'];
                $longitude = (float) $row['longitude'];
                self::validateCoordinates($latitude, $longitude);
                $location = $this->location($row, 'geojson-exact', propertyConfig: $reference ? 'geocoding.reference_properties' : 'geocoding.properties');

                // The bundled export includes two explicitly unmatched rows.
                if ($location->country === null && $location->countryCode === null && $location->city === null) {
                    continue;
                }

                $key = self::coordinateKey($latitude, $longitude);

                $previous = $references[$key] ?? null;
                $previous = $reference ? $previous : ($previous['location'] ?? null);

                if ($previous !== null && $previous->attributes() !== $location->attributes()) {
                    throw new InvalidArgumentException("Conflicting geocoding labels at coordinates [{$key}].");
                }

                $references[$key] = $reference ? $location : self::point($latitude, $longitude, $location);
            }
        } else {
            if (! $streaming && (($data['type'] ?? null) !== 'FeatureCollection' || ! isset($data['features']) || ! is_array($data['features']) || ! array_is_list($data['features']))) {
                throw new InvalidArgumentException('Expected a GeoJSON FeatureCollection with a features array.');
            }

            foreach ($streaming ? $data : $data['features'] as $index => $feature) {
                if (! is_array($feature) || ($feature['type'] ?? null) !== 'Feature' || ! is_array($feature['properties'] ?? null) || ! is_array($feature['geometry'] ?? null)) {
                    throw new InvalidArgumentException("GeoJSON feature [{$index}] must have properties and a geometry.");
                }

                $geometry = $feature['geometry'];
                $properties = $feature['properties'];
                $type = $geometry['type'] ?? null;

                if (in_array($type, ['Polygon', 'MultiPolygon'], true)) {
                    $location = $this->location($properties, polygon: true);

                    if ($location->country === null && $location->countryCode === null) {
                        throw new InvalidArgumentException("Country feature [{$index}] is missing a country name or code. Configure geocoding.properties for the source's schema.");
                    }

                    self::validatePolygon($geometry);
                    $countries[] = [
                        'area' => CountryGeometry::fromArray($geometry),
                        'location' => $location,
                        'key' => self::countryKey($location),
                    ];
                } elseif ($type === 'Point') {
                    $location = $this->location($properties, cityPoint: true);

                    if ($location->city === null) {
                        throw new InvalidArgumentException("City feature [{$index}] is missing a city name. Configure geocoding.properties for the source's schema.");
                    }

                    [$longitude, $latitude] = self::position($geometry['coordinates'] ?? null);
                    $cities[] = self::point($latitude, $longitude, $location);
                } else {
                    throw new InvalidArgumentException("Unsupported geometry in feature [{$index}]; use country Polygon/MultiPolygon and city Point features.");
                }

                unset($feature, $geometry, $properties);
            }
        }

        if ($references === [] && $countries === [] && $cities === []) {
            throw new InvalidArgumentException('The geocoding source has no labelled reference points, country boundaries, or cities.');
        }

        $citiesByCountry = [];

        foreach ($cities as $city) {
            $location = $city['location'];

            if ($countries === [] && $location->country === null && $location->countryCode === null) {
                throw new InvalidArgumentException('City-only GeoJSON must include a country name or code on every city.');
            }

            $assigned = [];

            foreach ($countries as $country) {
                $countryLocation = $country['location'];
                $belongs = match (true) {
                    $location->countryCode !== null && $countryLocation->countryCode !== null => $location->countryCode === $countryLocation->countryCode,
                    $location->country !== null && $countryLocation->country !== null => strcasecmp($location->country, $countryLocation->country) === 0,
                    default => $country['area']->contains($city['longitude'], $city['latitude']),
                };

                if ($belongs && ! isset($assigned[$country['key']])) {
                    $citiesByCountry[$country['key']][] = $city;
                    $assigned[$country['key']] = true;
                }
            }
        }

        return [$references, $countries, $cities, $citiesByCountry];
    }

    private function location(array $properties, string $source = 'geojson', bool $polygon = false, bool $cityPoint = false, string $propertyConfig = 'geocoding.properties'): GeocodedLocation
    {
        $country = $this->property($properties, 'country', ['country', 'country_name', 'COUNTRY', 'ADMIN', 'admin'], $propertyConfig);

        if ($country === null && $polygon) {
            $country = $this->property($properties, 'country', ['name', 'NAME'], $propertyConfig);
        }

        $code = $this->property($properties, 'country_code', ['country_code', 'countryCode', 'iso_a2', 'ISO_A2', 'ISO_A2_EH'], $propertyConfig);
        $city = $polygon ? null : $this->property($properties, 'city', ['city', 'city_name'], $propertyConfig);

        if ($city === null && $cityPoint) {
            $city = $this->property($properties, 'city', ['name', 'NAME'], $propertyConfig);
        }

        $code = $code === null || $code === '-99' ? null : mb_strtoupper($code);

        if ($code !== null && ! preg_match('/^[A-Z]{2,3}$/D', $code)) {
            throw new InvalidArgumentException('Geocoding country codes must be two or three ASCII letters.');
        }

        return new GeocodedLocation(
            $country,
            $code,
            $city,
            $this->property($properties, 'country_ru', ['country_ru', 'countryRu'], $propertyConfig),
            $this->property($properties, 'region', ['region', 'state', 'admin1'], $propertyConfig),
            $source,
            cityRu: $this->property($properties, 'city_ru', ['city_ru', 'cityRu'], $propertyConfig),
            regionRu: $this->property($properties, 'region_ru', ['region_ru', 'regionRu'], $propertyConfig),
            geonamesId: $this->identifier($properties, ['geonames_id', 'geonamesId', 'geonameid']),
        );
    }

    private function identifier(array $properties, array $keys): ?int
    {
        foreach ($keys as $key) {
            $value = $properties[$key] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            if ((! is_string($value) && ! is_int($value)) || ! ctype_digit((string) $value) || (int) $value < 1) {
                throw new InvalidArgumentException("Geocoding property [{$key}] must be a positive integer identifier.");
            }

            return (int) $value;
        }

        return null;
    }

    private function pointLocation(?array $nearest): ?GeocodedLocation
    {
        if ($nearest === null) {
            return null;
        }

        $point = $nearest['point'];
        $location = $point['location'];

        return new GeocodedLocation(
            $location->country, $location->countryCode, $location->city,
            $location->countryRu, $location->region, $location->source,
            cityRu: $location->cityRu,
            regionRu: $location->regionRu,
            nearestCityDistanceKm: $nearest['distance'],
            geonamesId: $location->geonamesId,
            cityLatitude: $point['latitude'],
            cityLongitude: $point['longitude'],
            cityAdmin1Code: $location->cityAdmin1Code,
        );
    }

    private function property(array $properties, string $field, array $defaults, string $propertyConfig): ?string
    {
        $keys = $this->config->get("{$propertyConfig}.{$field}", $defaults);
        $keys = is_string($keys) ? [$keys] : $keys;

        if (! is_array($keys) || ! array_is_list($keys) || array_filter($keys, fn ($key) => ! is_string($key)) !== []) {
            throw new InvalidArgumentException("{$propertyConfig}.{$field} must be a property name or list of property names.");
        }

        foreach ($keys as $key) {
            $value = $properties[$key] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            if (! is_string($value)) {
                throw new InvalidArgumentException("Geocoding property [{$key}] must be a string or null.");
            }

            $value = mb_trim($value);

            if (mb_strlen($value) > 255) {
                throw new InvalidArgumentException("Geocoding property [{$key}] exceeds the database limit of 255 characters.");
            }

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function nearest(array $points, float $latitude, float $longitude): ?array
    {
        $latitude = deg2rad($latitude);
        $longitude = deg2rad($longitude);
        $cosLatitude = cos($latitude);
        $minimum = INF;
        $nearest = null;

        foreach ($points as $point) {
            $distance = sin(($point['latitudeRadians'] - $latitude) / 2) ** 2
                + $cosLatitude * $point['cosLatitude'] * sin(($point['longitudeRadians'] - $longitude) / 2) ** 2;

            if ($distance < $minimum) {
                $minimum = $distance;
                $nearest = $point;
            }
        }

        return $nearest === null ? null : [
            'point' => $nearest,
            'distance' => 2 * 6371.0088 * asin(sqrt(min(1, max(0, $minimum)))),
        ];
    }
}
