<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\GeographyCatalog;
use App\Contracts\ReverseGeocoder;
use App\Services\Geocoding\GeocodedLocation;
use App\Services\Geocoding\GeographyReverseGeocoder;
use App\Services\Geocoding\RegionBoundaryIndex;
use App\Services\Geocoding\RussiaBorderPolicy;
use Illuminate\Config\Repository;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class GeographyReverseGeocoderTest extends TestCase
{
    private array $paths = [];

    protected function tearDown(): void
    {
        foreach ($this->paths as $path) {
            unlink($path);
        }

        parent::tearDown();
    }

    public function test_region_is_containment_and_city_can_belong_to_another_country(): void
    {
        $path = $this->source([
            $this->feature('AA', 'geonames-admin1:AA.01', 'Source region', 101, 0, 0, 5, 5),
            $this->feature('BB', 'geonames-admin1:BB.02', 'City region', 102, 5, 0, 10, 5),
        ]);
        $base = $this->base(new GeocodedLocation('Country A', 'AA', 'Cross-border city', region: 'City region', nearestCityDistanceKm: 2.5, geonamesId: 700));
        $catalog = new IdentityTestCatalog;
        $geocoder = new GeographyReverseGeocoder($base, new RegionBoundaryIndex(new Repository(['geocoding' => ['regions_path' => $path]])), $catalog);
        $result = $geocoder->reverse(2, 4.9);

        self::assertSame(1, $result->countryId);
        self::assertSame(101, $result->regionId);
        self::assertSame('Source region', $result->region);
        self::assertSame('Исходный регион', $result->regionRu);
        self::assertSame(700, $result->nearestCityId);
        self::assertSame('Город за границей', $result->cityRu);
        self::assertSame(2.5, $result->nearestCityDistanceKm);
        self::assertSame('geonames-admin1:AA.01', $catalog->lastRegion?->regionSourceKey);
    }

    public function test_final_russia_policy_supplies_country_and_claimed_subject_identity(): void
    {
        $regions = $this->source([$this->feature('UA', 'geonames-admin1:UA.17', 'Mykolaiv', 700000, 0, 0, 5, 5)]);
        $border = $this->source([$this->feature('RU', 'unused', 'unused', null, 0, 0, 5, 5, ['region' => 'Херсонская область', 'source_code' => 'OSM-R1738982'])]);
        $config = new Repository(['geocoding' => ['regions_path' => $regions, 'russia_border_path' => $border]]);
        $base = $this->base(new GeocodedLocation('Ukraine', 'UA', 'Nearest', geonamesId: 700));
        $policy = new RussiaBorderPolicy($base, $config);
        $catalog = new IdentityTestCatalog;
        $result = (new GeographyReverseGeocoder($policy, new RegionBoundaryIndex($config, $policy), $catalog))->reverse(2, 2);

        self::assertSame('Russia', $result->country);
        self::assertSame('Россия', $result->countryRu);
        self::assertSame('RU', $result->countryCode);
        self::assertSame(2, $result->countryId);
        self::assertSame(706442, $result->regionId);
        self::assertSame('geonames-admin1:UA.08', $result->regionSourceKey);
        self::assertSame(700, $result->nearestCityId);
    }

    public function test_custom_sources_without_city_identifiers_keep_labels_without_false_links(): void
    {
        $base = $this->base(new GeocodedLocation('Custom country', 'ZZ', 'Custom city', region: 'Nearest city region'));
        $result = (new GeographyReverseGeocoder($base, new RegionBoundaryIndex(new Repository), new IdentityTestCatalog))->reverse(0, 0);

        self::assertSame('Custom country', $result->country);
        self::assertSame('Custom city', $result->city);
        self::assertNull($result->countryId);
        self::assertNull($result->nearestCityId);
        self::assertNull($result->region);
        self::assertNull($result->regionId);
    }

    public function test_region_replacement_is_seen_after_snapshot_including_same_size_same_mtime(): void
    {
        $first = $this->feature('AA', 'geonames-admin1:AA.01', 'First', 101, 0, 0, 5, 5);
        $path = $this->source([$first]);
        $config = new Repository(['geocoding' => ['regions_path' => $path]]);
        $index = new RegionBoundaryIndex($config);
        $time = filemtime($path);
        $index->withSnapshot(function () use ($index, $path, $time, $first): void {
            self::assertSame('First', $index->region(2, 2)->region);
            $first['properties']['name'] = 'Other';
            file_put_contents($path, json_encode(['type' => 'FeatureCollection', 'features' => [$first]]));
            touch($path, $time);
            self::assertSame('First', $index->region(2, 2)->region);
        });
        self::assertSame('Other', $index->region(2, 2)->region);
    }

    public function test_invalid_region_replacement_throws_instead_of_exposing_old_match(): void
    {
        $path = $this->source([$this->feature('AA', 'geonames-admin1:AA.01', 'First', 101, 0, 0, 5, 5)]);
        $index = new RegionBoundaryIndex(new Repository(['geocoding' => ['regions_path' => $path]]));
        self::assertSame('First', $index->region(2, 2)->region);
        file_put_contents($path, '{invalid');
        $this->expectException(RuntimeException::class);
        $index->region(2, 2);
    }

    public function test_country_geometry_cache_recovers_from_corruption_and_missing_partition(): void
    {
        $path = $this->source([
            $this->feature('AA', 'geonames-admin1:AA.01', 'First', 101, 0, 0, 5, 5),
            $this->feature('BB', 'geonames-admin1:BB.01', 'Second', 102, 5, 0, 10, 5),
        ]);
        $directory = sys_get_temp_dir().'/rodnik-region-cache-'.bin2hex(random_bytes(8));
        mkdir($directory);

        try {
            $config = new Repository(['geocoding' => ['regions_path' => $path, 'index_cache_path' => $directory]]);
            $index = new RegionBoundaryIndex($config);
            self::assertSame('First', $index->region(2, 2, 'AA')->region);
            $cache = glob($directory.'/*-AA.bin')[0];
            file_put_contents($cache, 'corrupt');
            $index = new RegionBoundaryIndex($config);
            self::assertSame('First', $index->region(2, 2, 'AA')->region);
            unlink($cache);
            $index = new RegionBoundaryIndex($config);
            self::assertSame('First', $index->region(2, 2, 'AA')->region);
            self::assertSame('Second', $index->region(2, 7, 'BB')->region);
            self::assertNull($index->region(2, 2, 'ZZ'));
        } finally {
            foreach (glob($directory.'/*') as $file) {
                unlink($file);
            }

            rmdir($directory);
        }
    }

    private function source(array $features): string
    {
        $path = tempnam(sys_get_temp_dir(), 'rodnik-region-test-');
        $this->paths[] = $path;
        file_put_contents($path, json_encode(['type' => 'FeatureCollection', 'features' => $features]));

        return $path;
    }

    private function feature(string $country, string $key, string $name, ?int $id, float $left, float $bottom, float $right, float $top, array $extra = []): array
    {
        return [
            'type' => 'Feature',
            'properties' => ['country_code' => $country, 'source_key' => $key, 'name' => $name, 'geonames_id' => $id] + $extra,
            'geometry' => ['type' => 'Polygon', 'coordinates' => [[[$left, $bottom], [$right, $bottom], [$right, $top], [$left, $top], [$left, $bottom]]]],
        ];
    }

    private function base(?GeocodedLocation $location): ReverseGeocoder
    {
        return new class($location) implements ReverseGeocoder
        {
            public function __construct(private readonly ?GeocodedLocation $location) {}

            public function validateSource(): void {}

            public function reverse(float $latitude, float $longitude): ?GeocodedLocation
            {
                return $this->location;
            }
        };
    }
}

final class IdentityTestCatalog implements GeographyCatalog
{
    public ?GeocodedLocation $lastRegion = null;

    public function country(?string $code): ?array
    {
        return match ($code) {
            'AA' => ['id' => 1, 'name' => 'Country A', 'name_ru' => 'Страна А'],
            'RU' => ['id' => 2, 'name' => 'Untrusted catalog label', 'name_ru' => 'Untrusted catalog label'],
            default => null,
        };
    }

    public function city(?int $geonamesId): ?array
    {
        return $geonamesId === null ? null : ['id' => $geonamesId, 'name' => 'Cross-border city', 'name_ru' => 'Город за границей'];
    }

    public function region(GeocodedLocation $location, ?int $countryId): ?array
    {
        $this->lastRegion = $location;

        return ['id' => $location->regionGeonamesId, 'name' => $location->region, 'name_ru' => $location->regionRu ?? 'Исходный регион'];
    }

    public function clear(): void {}
}
