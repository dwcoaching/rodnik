<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Geocoding\CitySpatialIndex;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CitySpatialIndexTest extends TestCase
{
    private string $directory;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/rodnik-city-index-'.bin2hex(random_bytes(8));
        mkdir($this->directory);
        $this->path = $this->directory.'/cities.txt';
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*') as $path) {
            unlink($path);
        }

        rmdir($this->directory);
        parent::tearDown();
    }

    public function test_tree_returns_the_same_spherical_nearest_city_as_exhaustive_search(): void
    {
        $points = [];
        $rows = [];

        for ($i = 0; $i < 1000; $i++) {
            $latitude = (($i * 7727) % 17800) / 100 - 89;
            $longitude = (($i * 6271) % 36000) / 100 - 180;
            $country = $i % 2 === 0 ? 'AA' : 'BB';
            $points[] = [$latitude, $longitude, $country, 'City '.$i];
            $rows[] = $this->row($i + 1, 'City '.$i, $country, $latitude, $longitude);
        }

        file_put_contents($this->path, implode('', $rows));
        $index = CitySpatialIndex::fromFiles($this->path);
        self::assertSame(1000, $index->count());

        for ($i = 0; $i < 40; $i++) {
            $latitude = (($i * 4133) % 18000) / 100 - 90;
            $longitude = (($i * 1127) % 36000) / 100 - 180;

            foreach ([null, 'AA', 'BB'] as $country) {
                $bestDistance = INF;
                $bestCity = null;

                foreach ($points as [$pointLatitude, $pointLongitude, $pointCountry, $city]) {
                    if ($country !== null && $pointCountry !== $country) {
                        continue;
                    }

                    $distance = sin(deg2rad($pointLatitude - $latitude) / 2) ** 2
                        + cos(deg2rad($latitude)) * cos(deg2rad($pointLatitude)) * sin(deg2rad($pointLongitude - $longitude) / 2) ** 2;

                    if ($distance < $bestDistance) {
                        $bestDistance = $distance;
                        $bestCity = $city;
                    }
                }

                self::assertSame($bestCity, $index->nearest($latitude, $longitude, $country)->city);
            }
        }
    }

    public function test_country_roots_aliases_duplicates_dateline_and_region_labels(): void
    {
        file_put_contents($this->path,
            $this->row(1, 'Dateline city', 'AA', 80, -179.9, '01')
            .$this->row(2, 'Country city', 'BB', 80.2, 179.9)
            .$this->row(3, 'Duplicate city', 'AA', 80, -179.9)
        );
        $adminPath = $this->directory.'/regions.txt';
        file_put_contents($adminPath, "AA.01\tRegion\tRegion\t123\n");
        $index = CitySpatialIndex::fromFiles($this->path, $adminPath);

        self::assertSame('Dateline city', $index->nearest(80, 179.9)->city);
        self::assertSame('Dateline city', $index->nearest(80, 179.9, ['BB', 'AA'])->city);
        self::assertSame('Country city', $index->nearest(80, 179.9, 'BB')->city);
        self::assertSame('Region', $index->nearest(80, 179.9, 'AA')->region);
        self::assertNull($index->nearest(80, 179.9, 'ZZ'));
    }

    public function test_city_identity_center_and_great_circle_distance_survive_compiled_cache(): void
    {
        file_put_contents($this->path, $this->row(12345, 'Center', 'AA', 0, 179, '09'));
        CitySpatialIndex::fromFiles($this->path, cacheDirectory: $this->directory);
        $index = CitySpatialIndex::fromFiles($this->path, cacheDirectory: $this->directory);
        $location = $index->nearest(0, -179);

        self::assertSame(12345, $location->geonamesId);
        self::assertSame('09', $location->cityAdmin1Code);
        self::assertEqualsWithDelta(0, $location->cityLatitude, 0.0000001);
        self::assertEqualsWithDelta(179, $location->cityLongitude, 0.0000001);
        self::assertEqualsWithDelta(222.39016, $location->nearestCityDistanceKm, 0.0001);
    }

    public function test_derived_cache_is_reusable_and_corruption_is_rebuilt_from_validated_source(): void
    {
        file_put_contents($this->path, $this->row(1, 'Original', 'AA', 0, 0));
        $index = CitySpatialIndex::fromFiles($this->path, cacheDirectory: $this->directory);
        self::assertSame('Original', $index->nearest(0, 0)->city);
        self::assertCount(1, glob($this->directory.'/*.bin'));
        $cache = glob($this->directory.'/*.bin')[0];
        self::assertSame('Original', CitySpatialIndex::fromFiles($this->path, cacheDirectory: $this->directory)->nearest(0, 0)->city);

        $contents = file_get_contents($cache);
        $contents[mb_strlen($contents, '8bit') - 1] = chr(ord($contents[mb_strlen($contents, '8bit') - 1]) ^ 1);
        file_put_contents($cache, $contents);
        self::assertSame('Original', CitySpatialIndex::fromFiles($this->path, cacheDirectory: $this->directory)->nearest(0, 0)->city);

        file_put_contents($cache, 'incomplete');
        self::assertSame('Original', CitySpatialIndex::fromFiles($this->path, cacheDirectory: $this->directory)->nearest(0, 0)->city);
    }

    public function test_replacing_cities_and_regions_invalidates_the_derived_cache(): void
    {
        $adminPath = $this->directory.'/regions.txt';
        file_put_contents($this->path, $this->row(1, 'City A', 'AA', 0, 0, '01'));
        file_put_contents($adminPath, "AA.01\tRegion A\tRegion A\t123\n");
        $signature = CitySpatialIndex::signature($this->path, $adminPath);
        $first = CitySpatialIndex::fromFiles($this->path, $adminPath, $this->directory);
        self::assertSame('City A', $first->nearest(0, 0)->city);
        self::assertSame('Region A', $first->nearest(0, 0)->region);

        file_put_contents($this->path, $this->row(1, 'City B', 'AA', 0, 0, '01'));
        file_put_contents($adminPath, "AA.01\tRegion B\tRegion B\t123\n");
        self::assertNotSame($signature, CitySpatialIndex::signature($this->path, $adminPath));
        $second = CitySpatialIndex::fromFiles($this->path, $adminPath, $this->directory);
        self::assertSame('City B', $second->nearest(0, 0)->city);
        self::assertSame('Region B', $second->nearest(0, 0)->region);
    }

    public function test_every_city_row_is_validated_before_an_index_is_accepted(): void
    {
        $valid = $this->row(1, 'Valid', 'AA', 0, 0);

        foreach (['', "id\tname\tlatitude\tlongitude\n", $valid.$this->row(2, str_repeat('Я', 256), 'AA', 0, 0), $valid.$this->row(2, 'Invalid', 'AAAA', 0, 0), $valid.$this->row(2, 'Invalid', 'AA', 91, 0)] as $contents) {
            file_put_contents($this->path, $contents);

            try {
                CitySpatialIndex::fromFiles($this->path);
                self::fail('An empty or malformed city source must not be accepted.');
            } catch (RuntimeException $exception) {
                self::assertNotSame('', $exception->getMessage());
            }
        }
    }

    public function test_raw_replacements_exclude_non_current_settlements_even_when_they_are_closer(): void
    {
        $rows = $this->row(1, 'Current city', 'AA', 0.01, 0);

        foreach (['PPLX', 'PPLQ', 'PPLH', 'PPLCH', 'PPLW'] as $index => $code) {
            $rows .= $this->row($index + 2, 'Excluded '.$code, 'AA', 0, 0, featureCode: $code);
        }

        file_put_contents($this->path, $rows);
        $index = CitySpatialIndex::fromFiles($this->path);

        self::assertSame(1, $index->count());
        self::assertSame('Current city', $index->nearest(0, 0)->city);
        self::assertSame('Current city', $index->nearest(0, 0, 'AA')->city);
    }

    public function test_sources_with_only_excluded_settlements_are_rejected(): void
    {
        file_put_contents($this->path, $this->row(1, 'Historical city', 'AA', 0, 0, featureCode: 'PPLH'));
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('contains no valid cities');

        CitySpatialIndex::fromFiles($this->path);
    }

    public function test_replacements_cannot_publish_new_data_under_a_previously_captured_signature(): void
    {
        file_put_contents($this->path, $this->row(1, 'Original', 'AA', 0, 0));
        $originalSignature = CitySpatialIndex::signature($this->path);
        file_put_contents($this->path, $this->row(1, 'Replacement', 'AA', 0, 0));

        try {
            CitySpatialIndex::fromFiles($this->path, cacheDirectory: $this->directory, signature: $originalSignature);
            self::fail('A changed source must not be cached under the old signature.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('changed while its index was being built', $exception->getMessage());
        }

        self::assertSame([], glob($this->directory.'/*.bin'));
        file_put_contents($this->path, $this->row(1, 'Original', 'AA', 0, 0));
        $restored = CitySpatialIndex::fromFiles($this->path, cacheDirectory: $this->directory);
        self::assertSame('Original', $restored->nearest(0, 0)->city);
    }

    private function row(int $id, string $city, string $country, float $latitude, float $longitude, string $admin = '', string $featureCode = 'PPL'): string
    {
        $columns = array_fill(0, 19, '');
        $columns[0] = (string) $id;
        $columns[1] = $city;
        $columns[4] = (string) $latitude;
        $columns[5] = (string) $longitude;
        $columns[6] = 'P';
        $columns[7] = $featureCode;
        $columns[8] = $country;
        $columns[10] = $admin;

        return implode("\t", $columns)."\n";
    }
}
