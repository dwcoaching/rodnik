<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Geocoding\GeoJsonReverseGeocoder;
use Illuminate\Config\Repository;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class GeoJsonReverseGeocoderTest extends TestCase
{
    private string $path;

    private Repository $config;

    private array $extraPaths = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->path = tempnam(sys_get_temp_dir(), 'rodnik-geocoding-');
        $this->config = new Repository(['geocoding' => ['path' => $this->path]]);
    }

    protected function tearDown(): void
    {
        unlink($this->path);

        foreach ($this->extraPaths as $path) {
            unlink($path);
        }

        parent::tearDown();
    }

    public function test_export_matches_coordinates_without_using_source_ids(): void
    {
        $this->write([[
            'id' => 99999,
            'latitude' => '55.655106',
            'longitude' => '36.709984',
            'country' => 'Russia',
            'country_ru' => 'Россия',
            'country_code' => 'ru',
            'region' => 'Moskovskaya',
            'city' => 'Karinskoye',
        ], [
            'latitude' => '0.000000',
            'longitude' => '0.000000',
            'country' => null,
            'city' => null,
        ]]);
        $geocoder = new GeoJsonReverseGeocoder($this->config);
        $geocoder->validateSource();

        $location = $geocoder->reverse(55.655106, 36.709984);

        self::assertSame('Russia', $location->country);
        self::assertSame('RU', $location->countryCode);
        self::assertSame('Россия', $location->countryRu);
        self::assertSame('Karinskoye', $location->city);
        self::assertSame('geojson-exact', $location->source);
        self::assertNull($geocoder->reverse(55.655107, 36.709984));
        self::assertNull($geocoder->reverse(0, 0));
    }

    public function test_bundled_export_is_valid_and_keeps_its_exact_labels(): void
    {
        $this->config->set('geocoding.path', 'resources/geocoding/rodnik-with-countries.json');
        $geocoder = new GeoJsonReverseGeocoder($this->config);
        $geocoder->validateSource();

        self::assertSame('Karinskoye', $geocoder->reverse(55.655106, 36.709984)->city);
        self::assertSame('WS', $geocoder->reverse(-14.011750, -171.778325)->countryCode);
    }

    public function test_reference_approximation_is_explicit_and_bounded(): void
    {
        $this->write([['latitude' => 0, 'longitude' => 0, 'country' => 'Example', 'city' => 'Origin']]);
        $geocoder = new GeoJsonReverseGeocoder($this->config);

        self::assertNull($geocoder->reverse(0.001, 0));
        $this->config->set('geocoding.reference_max_distance_km', 1);
        self::assertSame('Origin', $geocoder->reverse(0.001, 0)->city);
        self::assertSame('geojson-nearby-reference', $geocoder->reverse(0.001, 0)->source);
        self::assertNull($geocoder->reverse(1, 0));
    }

    public function test_equivalent_duplicate_coordinates_and_negative_zero_match_the_same_reference(): void
    {
        $this->write([
            ['latitude' => 0, 'longitude' => 0, 'country' => 'Example', 'city' => 'Origin'],
            ['latitude' => '-0.000000', 'longitude' => '-0.000000', 'country' => 'Example', 'city' => 'Origin'],
        ]);
        $geocoder = new GeoJsonReverseGeocoder($this->config);

        self::assertSame('Origin', $geocoder->reverse(-0.0000001, -0.0000001)->city);
    }

    public function test_worker_observes_replacements_with_identical_size_and_timestamp(): void
    {
        $this->write([['latitude' => 0, 'longitude' => 0, 'country' => 'Example', 'city' => 'City A']]);
        $originalTimestamp = filemtime($this->path);
        $originalSize = filesize($this->path);
        $geocoder = new GeoJsonReverseGeocoder($this->config);

        self::assertSame('City A', $geocoder->reverse(0, 0)->city);
        $this->write([['latitude' => 0, 'longitude' => 0, 'country' => 'Example', 'city' => 'City B']]);
        touch($this->path, $originalTimestamp);
        clearstatcache(true, $this->path);

        self::assertSame($originalSize, filesize($this->path));
        self::assertSame($originalTimestamp, filemtime($this->path));
        self::assertSame('City B', $geocoder->reverse(0, 0)->city);
    }

    public function test_country_containment_and_nearest_city_respect_country_boundaries(): void
    {
        $this->config->set('geocoding.city_scope', 'country');
        $this->features([
            $this->country('West', 'AA', $this->square(0, 0, 10, 10)),
            $this->country('East', 'BB', $this->square(10, 0, 20, 10)),
            $this->city('Farther western city', 'AA', 2, 2),
            $this->city('Nearest western city', 'AA', 8, 5),
            $this->city('Closer foreign city', 'BB', 10.01, 5),
        ]);
        $location = (new GeoJsonReverseGeocoder($this->config))->reverse(5, 9.99);

        self::assertSame('West', $location->country);
        self::assertSame('AA', $location->countryCode);
        self::assertSame('Nearest western city', $location->city);
    }

    public function test_global_nearest_city_may_cross_a_border_without_changing_the_country(): void
    {
        $foreignCity = $this->city('Closer foreign city', 'BB', 10.01, 5);
        $foreignCity['properties']['region'] = 'Foreign region';
        $this->features([
            $this->country('West', 'AA', $this->square(0, 0, 10, 10)),
            $this->country('East', 'BB', $this->square(10, 0, 20, 10)),
            $this->city('Farther western city', 'AA', 2, 2),
            $foreignCity,
        ]);
        $location = (new GeoJsonReverseGeocoder($this->config))->reverse(5, 9.99);

        self::assertSame('West', $location->country);
        self::assertSame('AA', $location->countryCode);
        self::assertSame('Closer foreign city', $location->city);
        self::assertNull($location->region);
    }

    public function test_holes_multipolygons_zero_coordinates_and_unnamed_country_associations(): void
    {
        $country = $this->country('Islands', 'AA', [
            $this->square(0, 0, 4, 4),
            $this->square(10, 10, 12, 12),
        ], 'MultiPolygon');
        $country['geometry']['coordinates'][0][] = $this->square(1, 1, 3, 3)[0];
        $this->features([
            $country,
            $this->city('Mainland', null, 0.5, 0.5),
            $this->city('Island', null, 11, 11),
        ]);
        $geocoder = new GeoJsonReverseGeocoder($this->config);

        self::assertSame('Mainland', $geocoder->reverse(0, 0)->city);
        self::assertNull($geocoder->reverse(2, 2));
        self::assertNull($geocoder->reverse(1, 1));
        self::assertSame('Island', $geocoder->reverse(11, 11)->city);
        self::assertSame('AA', $geocoder->reverse(11, 11)->countryCode);
        self::assertNull($geocoder->reverse(5, 5));
    }

    public function test_city_only_sources_use_spherical_distance_across_the_dateline(): void
    {
        $this->features([
            $this->city('Across dateline', 'AA', -179.9, 80),
            $this->city('Farther north', 'AA', 179.9, 80.2),
        ]);
        $location = (new GeoJsonReverseGeocoder($this->config))->reverse(80, 179.9);

        self::assertSame('Across dateline', $location->city);
        self::assertSame('AA', $location->countryCode);
    }

    public function test_country_without_city_coverage_returns_country_without_inventing_a_city(): void
    {
        $this->features([$this->country('Example', 'AA', $this->square(0, 0, 2, 2))]);
        $location = (new GeoJsonReverseGeocoder($this->config))->reverse(1, 1);

        self::assertSame('Example', $location->country);
        self::assertNull($location->city);
    }

    public function test_replacement_schemas_can_configure_property_names(): void
    {
        $country = $this->country('ignored', 'ignored', $this->square(0, 0, 2, 2));
        $country['properties'] = ['countryLabel' => 'Example', 'iso' => 'aa'];
        $city = $this->city('ignored', 'ignored', 1, 1);
        $city['properties'] = ['countryLabel' => 'Example', 'iso' => 'aa', 'settlement' => 'Capital', 'province' => 'Region'];
        $this->features([$country, $city]);
        $this->config->set('geocoding.properties', [
            'country' => 'countryLabel',
            'country_code' => 'iso',
            'city' => ['settlement', 'other_city'],
            'region' => 'province',
        ]);
        $location = (new GeoJsonReverseGeocoder($this->config))->reverse(1, 1);

        self::assertSame('Example', $location->country);
        self::assertSame('AA', $location->countryCode);
        self::assertSame('Capital', $location->city);
        self::assertSame('Region', $location->region);
    }

    public function test_validation_checks_every_record_before_accepting_a_source(): void
    {
        $this->write([
            ['latitude' => 0, 'longitude' => 0, 'country' => 'Example', 'city' => 'Valid'],
            ['latitude' => 'invalid', 'longitude' => 0, 'country' => 'Example', 'city' => 'Invalid'],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Spring reference [1]');
        (new GeoJsonReverseGeocoder($this->config))->validateSource();
    }

    public function test_invalid_replacement_cannot_silently_reuse_old_labels(): void
    {
        $this->write([['latitude' => 0, 'longitude' => 0, 'country' => 'Example', 'city' => 'Old']]);
        $geocoder = new GeoJsonReverseGeocoder($this->config);
        self::assertSame('Old', $geocoder->reverse(0, 0)->city);
        file_put_contents($this->path, '{invalid json');

        try {
            $geocoder->reverse(0, 0);
            self::fail('Invalid replacements must fail instead of returning cached data.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('Invalid geocoding source', $exception->getMessage());
        }

        $this->write([['latitude' => 0, 'longitude' => 0, 'country' => 'Example', 'city' => 'New']]);
        self::assertSame('New', $geocoder->reverse(0, 0)->city);
    }

    public function test_empty_or_unlabelled_sources_are_rejected(): void
    {
        foreach ([[], ['type' => 'FeatureCollection', 'features' => []], [['latitude' => 0, 'longitude' => 0]]] as $data) {
            $this->write($data);

            try {
                (new GeoJsonReverseGeocoder($this->config))->validateSource();
                self::fail('A source without usable labels must be rejected.');
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('Invalid geocoding source', $exception->getMessage());
            }
        }
    }

    public function test_malformed_and_unsupported_geometries_are_rejected(): void
    {
        $valid = $this->country('Example', 'AA', $this->square(0, 0, 2, 2));
        $unclosed = $valid;
        array_pop($unclosed['geometry']['coordinates'][0]);
        $invalidPosition = $valid;
        $invalidPosition['geometry']['coordinates'][0][1] = ['not numeric', 0];
        $twoDistinctPositions = $valid;
        $twoDistinctPositions['geometry']['coordinates'][0] = [[0, 0], [1, 1], [0, 0], [1, 1], [0, 0]];
        $unsupported = $this->city('Example', 'AA', 1, 1);
        $unsupported['geometry']['type'] = 'LineString';
        $unlabelledCity = $this->city('Example', null, 1, 1);

        foreach ([$unclosed, $invalidPosition, $twoDistinctPositions, $unsupported, $unlabelledCity] as $feature) {
            $this->features([$feature]);

            try {
                (new GeoJsonReverseGeocoder($this->config))->validateSource();
                self::fail('Malformed or unlabelled geometry must be rejected.');
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('Invalid geocoding source', $exception->getMessage());
            }
        }
    }

    public function test_invalid_input_coordinates_are_rejected(): void
    {
        $this->write([['latitude' => 0, 'longitude' => 0, 'country' => 'Example', 'city' => 'Origin']]);
        $this->expectException(InvalidArgumentException::class);

        (new GeoJsonReverseGeocoder($this->config))->reverse(91, 0);
    }

    public function test_schema_validation_rejects_labels_that_cannot_be_persisted(): void
    {
        foreach (['country', 'country_ru', 'city', 'region', 'country_code'] as $field) {
            $row = ['latitude' => 0, 'longitude' => 0, 'country' => 'Example', 'city' => 'Origin'];
            $row[$field] = $field === 'country_code' ? 'LONG' : str_repeat('Я', 256);
            $this->write([$row]);

            try {
                (new GeoJsonReverseGeocoder($this->config))->validateSource();
                self::fail("The field [{$field}] must respect the database schema before a reset starts.");
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('Invalid geocoding source', $exception->getMessage());
            }
        }

        $this->config->set('geocoding.properties.city', 'settlement');
        $this->write([['latitude' => 0, 'longitude' => 0, 'country' => 'Example', 'settlement' => str_repeat('x', 256)]]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('settlement');
        (new GeoJsonReverseGeocoder($this->config))->validateSource();
    }

    public function test_external_geonames_cities_resolve_arbitrary_coordinates_and_keep_country_independent(): void
    {
        $this->features([$this->country('West', 'AA', $this->square(0, 0, 10, 10))]);
        $cityPath = $this->temporary($this->geoNamesRow('Far western city', 'AA', 2, 2)
            .$this->geoNamesRow('Near eastern city', 'BB', 5, 10.01, '01'));
        $adminPath = $this->temporary("BB.01\tForeign region\tForeign region\t1\n");
        $this->config->set('geocoding.cities_path', $cityPath);
        $this->config->set('geocoding.admin1_path', $adminPath);
        $geocoder = new GeoJsonReverseGeocoder($this->config);
        $location = $geocoder->reverse(5, 9.99);

        self::assertSame('AA', $location->countryCode);
        self::assertSame('Near eastern city', $location->city);
        self::assertNull($location->region);
        self::assertSame('geojson-geonames', $location->source);

        $this->config->set('geocoding.city_scope', 'country');
        self::assertSame('Far western city', $geocoder->reverse(5, 9.99)->city);
        self::assertNull($geocoder->reverse(50, 50));
    }

    public function test_seed_fallback_cannot_override_real_geography_or_block_replacement_schemas(): void
    {
        $country = $this->country('ignored', 'ignored', $this->square(0, 0, 2, 2));
        $country['properties'] = ['nation' => 'Current country', 'iso' => 'AA'];
        $this->features([$country]);
        $cityPath = $this->temporary($this->geoNamesRow('Current city', 'AA', 1, 1));
        $referencePath = $this->temporary(json_encode([
            ['latitude' => 1, 'longitude' => 1, 'country' => 'Old country', 'country_code' => 'BB', 'city' => 'Old city'],
            ['latitude' => 20, 'longitude' => 20, 'country' => 'Known country', 'country_code' => 'CC', 'city' => 'Known city'],
        ], JSON_THROW_ON_ERROR));
        $this->config->set('geocoding.properties', ['country' => 'nation', 'country_code' => 'iso']);
        $this->config->set('geocoding.cities_path', $cityPath);
        $this->config->set('geocoding.reference_path', $referencePath);
        $geocoder = new GeoJsonReverseGeocoder($this->config);

        self::assertSame('Current country', $geocoder->reverse(1, 1)->country);
        self::assertSame('Current city', $geocoder->reverse(1, 1)->city);
        self::assertSame('Known city', $geocoder->reverse(20, 20)->city);
        self::assertSame('geojson-exact', $geocoder->reverse(20, 20)->source);

        $country['properties']['nation'] = 'Replacement country';
        $this->features([$country]);
        self::assertSame('Replacement country', $geocoder->reverse(1, 1)->country);
    }

    public function test_external_city_replacements_are_visible_in_an_existing_worker(): void
    {
        $this->features([$this->country('Example', 'AA', $this->square(0, 0, 2, 2))]);
        $cityPath = $this->temporary($this->geoNamesRow('City A', 'AA', 1, 1));
        $this->config->set('geocoding.cities_path', $cityPath);
        $geocoder = new GeoJsonReverseGeocoder($this->config);
        self::assertSame('City A', $geocoder->reverse(1, 1)->city);

        $timestamp = filemtime($cityPath);
        file_put_contents($cityPath, $this->geoNamesRow('City B', 'AA', 1, 1));
        touch($cityPath, $timestamp);
        self::assertSame('City B', $geocoder->reverse(1, 1)->city);
    }

    public function test_failed_external_city_replacement_can_be_retried_without_reusing_stale_labels(): void
    {
        $this->features([$this->country('Example', 'AA', $this->square(0, 0, 2, 2))]);
        $cityPath = $this->temporary($this->geoNamesRow('City A', 'AA', 1, 1));
        $this->config->set('geocoding.cities_path', $cityPath);
        $geocoder = new GeoJsonReverseGeocoder($this->config);
        self::assertSame('City A', $geocoder->reverse(1, 1)->city);
        file_put_contents($cityPath, 'Invalid replacement');

        try {
            $geocoder->reverse(1, 1);
            self::fail('An invalid replacement must not expose the previous city label.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('Invalid GeoNames city row', $exception->getMessage());
        }

        file_put_contents($cityPath, $this->geoNamesRow('City B', 'AA', 1, 1));
        self::assertSame('City B', $geocoder->reverse(1, 1)->city);
    }

    public function test_embedded_city_points_make_replacement_geojson_self_contained(): void
    {
        $this->features([
            $this->country('Example', 'AA', $this->square(0, 0, 2, 2)),
            $this->city('Embedded city', 'AA', 1, 1),
        ]);
        $cityPath = $this->temporary('This external city file is intentionally invalid.');
        $this->config->set('geocoding.cities_path', $cityPath);

        self::assertSame('Embedded city', (new GeoJsonReverseGeocoder($this->config))->reverse(1, 1)->city);
    }

    public function test_source_validation_checks_the_complete_external_city_dataset(): void
    {
        $this->features([$this->country('Example', 'AA', $this->square(0, 0, 2, 2))]);
        $cityPath = $this->temporary($this->geoNamesRow('Valid city', 'AA', 1, 1)."Invalid final row\n");
        $this->config->set('geocoding.cities_path', $cityPath);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid GeoNames city row [2]');

        (new GeoJsonReverseGeocoder($this->config))->validateSource();
    }

    public function test_chunk_snapshot_stays_consistent_and_the_next_lookup_observes_replacement(): void
    {
        $this->write([['latitude' => 0, 'longitude' => 0, 'country' => 'Example', 'city' => 'City A']]);
        $geocoder = new GeoJsonReverseGeocoder($this->config);
        $result = $geocoder->withSnapshot(function () use ($geocoder): string {
            self::assertSame('City A', $geocoder->reverse(0, 0)->city);
            $this->write([['latitude' => 0, 'longitude' => 0, 'country' => 'Example', 'city' => 'City B']]);
            self::assertSame('City A', $geocoder->reverse(0, 0)->city);
            $geocoder->withSnapshot(fn () => self::assertSame('City A', $geocoder->reverse(0, 0)->city));

            return 'complete';
        });

        self::assertSame('complete', $result);
        self::assertSame('City B', $geocoder->reverse(0, 0)->city);
        $geocoder->withSnapshot(fn () => self::assertSame('City B', $geocoder->reverse(0, 0)->city));
    }

    public function test_snapshot_callback_failure_always_restores_source_reloading(): void
    {
        $this->write([['latitude' => 0, 'longitude' => 0, 'country' => 'Example', 'city' => 'City A']]);
        $geocoder = new GeoJsonReverseGeocoder($this->config);

        try {
            $geocoder->withSnapshot(function (): void {
                $this->write([['latitude' => 0, 'longitude' => 0, 'country' => 'Example', 'city' => 'City B']]);
                throw new RuntimeException('Callback failed.');
            });
            self::fail('The callback must propagate its failure.');
        } catch (RuntimeException $exception) {
            self::assertSame('Callback failed.', $exception->getMessage());
        }

        self::assertSame('City B', $geocoder->reverse(0, 0)->city);
    }

    private function temporary(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'rodnik-geocoding-');
        file_put_contents($path, $contents);
        $this->extraPaths[] = $path;

        return $path;
    }

    private function geoNamesRow(string $city, string $country, float $latitude, float $longitude, string $admin = ''): string
    {
        $columns = array_fill(0, 19, '');
        $columns[0] = '1';
        $columns[1] = $city;
        $columns[4] = (string) $latitude;
        $columns[5] = (string) $longitude;
        $columns[6] = 'P';
        $columns[7] = 'PPL';
        $columns[8] = $country;
        $columns[10] = $admin;

        return implode("\t", $columns)."\n";
    }

    private function write(array $data): void
    {
        file_put_contents($this->path, json_encode($data, JSON_THROW_ON_ERROR));
    }

    private function features(array $features): void
    {
        $this->write(['type' => 'FeatureCollection', 'features' => $features]);
    }

    private function country(string $name, string $code, array $coordinates, string $type = 'Polygon'): array
    {
        return [
            'type' => 'Feature',
            'properties' => ['country' => $name, 'country_code' => $code],
            'geometry' => ['type' => $type, 'coordinates' => $coordinates],
        ];
    }

    private function city(string $name, ?string $code, float $longitude, float $latitude): array
    {
        return [
            'type' => 'Feature',
            'properties' => ['city' => $name, 'country_code' => $code],
            'geometry' => ['type' => 'Point', 'coordinates' => [$longitude, $latitude]],
        ];
    }

    private function square(float $west, float $south, float $east, float $north): array
    {
        return [[[$west, $south], [$east, $south], [$east, $north], [$west, $north], [$west, $south]]];
    }
}
