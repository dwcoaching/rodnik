<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\NearestCityLocator;
use App\Contracts\ReverseGeocoder;
use App\Services\Geocoding\GeocodedLocation;
use App\Services\Geocoding\GeoJsonReverseGeocoder;
use App\Services\Geocoding\RussiaBorderPolicy;
use Illuminate\Config\Repository;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RussiaBorderPolicyTest extends TestCase
{
    private string $path;

    private array $extraPaths = [];

    private Repository $config;

    private PolicyTestGeocoder $base;

    protected function setUp(): void
    {
        parent::setUp();
        $this->path = tempnam(sys_get_temp_dir(), 'rodnik-border-policy-');
        $this->config = new Repository(['geocoding' => ['russia_border_path' => $this->path]]);
        $this->base = new PolicyTestGeocoder;
        $this->features([$this->polygon($this->square(0, 0, 4, 4))]);
    }

    protected function tearDown(): void
    {
        unlink($this->path);

        foreach ($this->extraPaths as $path) {
            unlink($path);
        }

        parent::tearDown();
    }

    public function test_claimed_border_country_override_is_final_including_exact_reference_labels(): void
    {
        $this->base->location = new GeocodedLocation('Ukraine', 'UA', 'Nearest settlement', 'Украина', 'Foreign region', 'geojson-exact');
        $policy = new RussiaBorderPolicy($this->base, $this->config);
        $location = $policy->reverse(2, 2);

        self::assertSame('Russia', $location->country);
        self::assertSame('Россия', $location->countryRu);
        self::assertSame('RU', $location->countryCode);
        self::assertSame('Nearest settlement', $location->city);
        self::assertNull($location->region);
        self::assertSame('russia-claimed-border', $location->source);
        self::assertSame('Ukraine', $this->base->location->country);
    }

    public function test_null_provider_result_still_receives_russian_country_and_global_nearest_city(): void
    {
        $this->base->nearest = new GeocodedLocation(null, 'UA', 'Closest city', region: 'Foreign region', source: 'geonames');
        $location = (new RussiaBorderPolicy($this->base, $this->config))->reverse(2, 2);

        self::assertSame('RU', $location->countryCode);
        self::assertSame('Closest city', $location->city);
        self::assertNull($location->region);
        self::assertSame(1, $this->base->nearestCalls);

        $this->base->nearest = null;
        $location = (new RussiaBorderPolicy($this->base, $this->config))->reverse(2, 2);
        self::assertSame('Russia', $location->country);
        self::assertNull($location->city);
    }

    public function test_overlay_regions_override_provider_regions_and_russian_regions_are_preserved(): void
    {
        $this->base->location = new GeocodedLocation('Russia', 'RU', 'City', region: 'Existing Russian region');
        $policy = new RussiaBorderPolicy($this->base, $this->config);
        self::assertSame('Existing Russian region', $policy->reverse(2, 2)->region);

        $feature = $this->polygon($this->square(0, 0, 4, 4));
        $feature['properties']['region'] = 'Configured region';
        $this->features([$feature]);
        $this->base->location = new GeocodedLocation('Ukraine', 'UA', 'City', region: 'Provider region');
        self::assertSame('Configured region', $policy->reverse(2, 2)->region);
    }

    public function test_reference_only_replacement_keeps_external_nearest_city_available_to_the_border_policy(): void
    {
        $basePath = $this->temporary();
        file_put_contents($basePath, json_encode([[
            'latitude' => 10,
            'longitude' => 10,
            'country' => 'Ukraine',
            'country_code' => 'UA',
            'city' => 'Cached settlement',
        ]], JSON_THROW_ON_ERROR));
        $cityPath = $this->temporary();
        $row = array_fill(0, 19, '');
        $row[0] = '123';
        $row[1] = 'Closest settlement';
        $row[4] = '2';
        $row[5] = '2';
        $row[6] = 'P';
        $row[7] = 'PPL';
        $row[8] = 'UA';
        file_put_contents($cityPath, implode("\t", $row)."\n");
        $this->config->set('geocoding.path', $basePath);
        $this->config->set('geocoding.cities_path', $cityPath);
        $base = new GeoJsonReverseGeocoder($this->config);
        $policy = new RussiaBorderPolicy($base, $this->config);

        self::assertNull($base->reverse(2, 2));
        $location = $policy->reverse(2, 2);
        self::assertSame(['Russia', 'Россия', 'RU'], [$location?->country, $location?->countryRu, $location?->countryCode]);
        self::assertSame('Closest settlement', $location->city);
        self::assertNull($location->region);
        self::assertNull($policy->reverse(5, 5));
        self::assertSame('Cached settlement', $policy->reverse(10, 10)->city);
    }

    public function test_outside_points_holes_and_disjoint_multipolygons_follow_geometry(): void
    {
        $this->base->location = new GeocodedLocation('Other country', 'AA', 'City', region: 'Original region');
        $geometry = [
            [$this->square(0, 0, 4, 4)[0], $this->square(1, 1, 3, 3)[0]],
            $this->square(10, 10, 12, 12),
        ];
        $this->features([$this->polygon($geometry, 'MultiPolygon')]);
        $policy = new RussiaBorderPolicy($this->base, $this->config);

        self::assertSame('RU', $policy->reverse(0, 0)->countryCode);
        self::assertSame('RU', $policy->reverse(11, 11)->countryCode);
        self::assertSame($this->base->location, $policy->reverse(2, 2));
        self::assertSame($this->base->location, $policy->reverse(1, 1));
        self::assertSame($this->base->location, $policy->reverse(5, 5));
    }

    public function test_policy_can_be_explicitly_disabled(): void
    {
        $this->config->set('geocoding.russia_border_path', null);
        $this->base->location = new GeocodedLocation('Original', 'AA', 'City');

        self::assertSame($this->base->location, (new RussiaBorderPolicy($this->base, $this->config))->reverse(2, 2));
    }

    public function test_border_properties_are_optional_because_the_final_country_is_fixed(): void
    {
        $feature = $this->polygon($this->square(0, 0, 4, 4));
        $feature['properties'] = null;
        $this->features([$feature]);

        self::assertSame('RU', (new RussiaBorderPolicy($this->base, $this->config))->reverse(2, 2)->countryCode);
    }

    public function test_missing_configured_border_file_cannot_expose_a_provider_country(): void
    {
        $this->config->set('geocoding.russia_border_path', $this->path.'.missing');
        $this->base->location = new GeocodedLocation('Ukraine', 'UA', 'City');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Could not read Russia claimed-border source');

        (new RussiaBorderPolicy($this->base, $this->config))->reverse(2, 2);
    }

    public function test_border_only_audits_do_not_validate_or_query_the_base_geocoder(): void
    {
        $policy = new RussiaBorderPolicy($this->base, $this->config);
        $policy->validateBorders();
        $result = $policy->withBorderSnapshot(function () use ($policy): string {
            self::assertTrue($policy->contains(2, 2));
            self::assertFalse($policy->contains(5, 5));
            $this->features([$this->polygon($this->square(10, 10, 12, 12))]);
            self::assertTrue($policy->contains(2, 2));
            $policy->withBorderSnapshot(fn () => self::assertTrue($policy->contains(2, 2)));

            return 'audited';
        });

        self::assertSame('audited', $result);
        self::assertFalse($policy->contains(2, 2));
        self::assertTrue($policy->contains(11, 11));
        self::assertSame(0, $this->base->validationCalls);
        self::assertSame(0, $this->base->reverseCalls);
        self::assertSame(0, $this->base->nearestCalls);
    }

    public function test_border_only_snapshot_errors_restore_reloading_and_invalid_geometry_propagates(): void
    {
        $policy = new RussiaBorderPolicy($this->base, $this->config);

        try {
            $policy->withBorderSnapshot(function (): void {
                file_put_contents($this->path, 'Invalid replacement');
                throw new RuntimeException('Audit callback failed.');
            });
            self::fail('Audit callback exceptions must propagate.');
        } catch (RuntimeException $exception) {
            self::assertSame('Audit callback failed.', $exception->getMessage());
        }

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid Russia claimed-border source');
        $policy->contains(2, 2);
    }

    public function test_dateline_split_polygons_cover_both_longitude_signs_without_covering_the_world(): void
    {
        $this->features([$this->polygon([
            $this->square(179, 64, 180, 66),
            $this->square(-180, 64, -179, 66),
        ], 'MultiPolygon')]);
        $policy = new RussiaBorderPolicy($this->base, $this->config);

        self::assertTrue($policy->contains(65, 179.5));
        self::assertTrue($policy->contains(65, -179.5));
        self::assertTrue($policy->contains(65, 180));
        self::assertTrue($policy->contains(65, -180));
        self::assertFalse($policy->contains(65, 0));
    }

    public function test_workers_reload_same_size_same_timestamp_border_replacements(): void
    {
        $this->features([$this->polygon($this->square(0, 0, 2, 2))]);
        $this->base->location = new GeocodedLocation('Other country', 'AA', 'City');
        $policy = new RussiaBorderPolicy($this->base, $this->config);
        self::assertSame('RU', $policy->reverse(1, 1)->countryCode);
        $timestamp = filemtime($this->path);
        $size = filesize($this->path);
        $this->features([$this->polygon($this->square(4, 4, 6, 6))]);
        touch($this->path, $timestamp);
        clearstatcache(true, $this->path);

        self::assertSame($size, filesize($this->path));
        self::assertSame($timestamp, filemtime($this->path));
        self::assertSame('AA', $policy->reverse(1, 1)->countryCode);
        self::assertSame('RU', $policy->reverse(5, 5)->countryCode);
    }

    public function test_overlay_and_base_sources_are_both_stable_during_a_chunk_snapshot(): void
    {
        $basePath = $this->temporary();
        $this->baseFeatures($basePath, 'City A');
        $this->config->set('geocoding.path', $basePath);
        $policy = new RussiaBorderPolicy(new GeoJsonReverseGeocoder($this->config), $this->config);

        $policy->withSnapshot(function () use ($policy, $basePath): void {
            self::assertSame('RU', $policy->reverse(2, 2)->countryCode);
            self::assertSame('City A', $policy->reverse(2, 2)->city);
            $this->features([$this->polygon($this->square(10, 10, 12, 12))]);
            $this->baseFeatures($basePath, 'City B');
            self::assertSame('RU', $policy->reverse(2, 2)->countryCode);
            self::assertSame('City A', $policy->reverse(2, 2)->city);
            $policy->withSnapshot(fn () => self::assertSame('RU', $policy->reverse(2, 2)->countryCode));
        });

        self::assertSame('UA', $policy->reverse(2, 2)->countryCode);
        self::assertSame('City B', $policy->reverse(2, 2)->city);
        self::assertSame('RU', $policy->reverse(11, 11)->countryCode);
        self::assertSame('City B', $policy->reverse(11, 11)->city);
    }

    public function test_snapshot_failures_restore_border_and_base_reloading(): void
    {
        $this->base->location = new GeocodedLocation('Other', 'AA', 'City');
        $policy = new RussiaBorderPolicy($this->base, $this->config);

        try {
            $policy->withSnapshot(function (): void {
                $this->features([$this->polygon($this->square(10, 10, 12, 12))]);
                throw new RuntimeException('Callback failed.');
            });
            self::fail('The callback must propagate its exception.');
        } catch (RuntimeException $exception) {
            self::assertSame('Callback failed.', $exception->getMessage());
        }

        self::assertSame('AA', $policy->reverse(2, 2)->countryCode);
        self::assertSame('RU', $policy->reverse(11, 11)->countryCode);
    }

    public function test_invalid_overlay_is_rejected_before_provider_results_or_bulk_validation(): void
    {
        $this->base->location = new GeocodedLocation('Ukraine', 'UA', 'City');
        $policy = new RussiaBorderPolicy($this->base, $this->config);
        self::assertSame('RU', $policy->reverse(2, 2)->countryCode);
        file_put_contents($this->path, '{invalid replacement');

        try {
            $policy->reverse(2, 2);
            self::fail('An invalid overlay must never return a conflicting provider label.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('Invalid Russia claimed-border source', $exception->getMessage());
        }

        self::assertSame(1, $this->base->reverseCalls);

        try {
            $policy->validateSource();
            self::fail('Bulk reset must reject an invalid overlay before the base is validated.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('Invalid Russia claimed-border source', $exception->getMessage());
        }

        self::assertSame(0, $this->base->validationCalls);
    }

    public function test_empty_malformed_or_unpersistable_border_features_are_rejected(): void
    {
        $unclosed = $this->polygon($this->square(0, 0, 4, 4));
        array_pop($unclosed['geometry']['coordinates'][0]);
        $invalidPosition = $this->polygon($this->square(0, 0, 4, 4));
        $invalidPosition['geometry']['coordinates'][0][1] = [181, 0];
        $longRegion = $this->polygon($this->square(0, 0, 4, 4));
        $longRegion['properties']['region'] = str_repeat('Я', 256);

        foreach ([[], [$unclosed], [$invalidPosition], [$longRegion]] as $features) {
            $this->features($features);

            try {
                (new RussiaBorderPolicy($this->base, $this->config))->validateSource();
                self::fail('Invalid border data must not be accepted.');
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('Invalid Russia claimed-border source', $exception->getMessage());
            }
        }
    }

    private function temporary(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'rodnik-border-base-');
        $this->extraPaths[] = $path;

        return $path;
    }

    private function baseFeatures(string $path, string $city): void
    {
        $country = $this->polygon($this->square(0, 0, 4, 4));
        $country['properties'] = ['country' => 'Ukraine', 'country_code' => 'UA'];
        $point = ['type' => 'Feature', 'properties' => ['city' => $city, 'country_code' => 'UA'], 'geometry' => ['type' => 'Point', 'coordinates' => [2, 2]]];
        file_put_contents($path, json_encode(['type' => 'FeatureCollection', 'features' => [$country, $point]], JSON_THROW_ON_ERROR));
    }

    private function features(array $features): void
    {
        file_put_contents($this->path, json_encode(['type' => 'FeatureCollection', 'features' => $features], JSON_THROW_ON_ERROR));
    }

    private function polygon(array $coordinates, string $type = 'Polygon'): array
    {
        return ['type' => 'Feature', 'properties' => [], 'geometry' => ['type' => $type, 'coordinates' => $coordinates]];
    }

    private function square(float $west, float $south, float $east, float $north): array
    {
        return [[[$west, $south], [$east, $south], [$east, $north], [$west, $north], [$west, $south]]];
    }
}

final class PolicyTestGeocoder implements NearestCityLocator, ReverseGeocoder
{
    public ?GeocodedLocation $location = null;

    public ?GeocodedLocation $nearest = null;

    public int $reverseCalls = 0;

    public int $nearestCalls = 0;

    public int $validationCalls = 0;

    public function validateSource(): void
    {
        $this->validationCalls++;
    }

    public function reverse(float $latitude, float $longitude): ?GeocodedLocation
    {
        $this->reverseCalls++;

        return $this->location;
    }

    public function nearestCity(float $latitude, float $longitude): ?GeocodedLocation
    {
        $this->nearestCalls++;

        return $this->nearest;
    }
}
