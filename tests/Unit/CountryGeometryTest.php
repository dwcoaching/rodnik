<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Library\GeoJsonArea;
use App\Services\Geocoding\CountryGeometry;
use App\Services\Geocoding\CountryGeometryCache;
use App\Services\Geocoding\GeocodedLocation;
use PHPUnit\Framework\TestCase;

final class CountryGeometryTest extends TestCase
{
    public function test_packed_banded_rings_match_polygon_containment_including_boundaries_and_holes(): void
    {
        $outer = $this->ring(20, 10, 5, 1000);
        $hole = $this->ring(20, 10, 1, 100);
        $island = $this->ring(30, 10, 2, 100);
        $geometry = ['type' => 'MultiPolygon', 'coordinates' => [[$outer, $hole], [$island]]];
        $reference = GeoJsonArea::fromArray($geometry);
        $packed = CountryGeometry::fromArray($geometry);

        for ($i = 0; $i < 500; $i++) {
            $longitude = 12 + (($i * 773) % 2300) / 100;
            $latitude = 3 + (($i * 391) % 1500) / 100;
            self::assertSame($reference->contains($longitude, $latitude), $packed->contains($longitude, $latitude));
        }

        foreach ([$outer, $hole, $island] as $ring) {
            foreach ($ring as [$longitude, $latitude]) {
                self::assertSame($reference->contains($longitude, $latitude), $packed->contains($longitude, $latitude));
            }
        }
    }

    public function test_compiled_country_cache_preserves_geometry_and_rejects_corruption(): void
    {
        $directory = sys_get_temp_dir().'/rodnik-country-cache-'.bin2hex(random_bytes(8));
        $countries = [[
            'area' => CountryGeometry::fromArray(['type' => 'Polygon', 'coordinates' => [$this->ring(20, 10, 5, 100)]]),
            'location' => new GeocodedLocation('Example', 'AA', null),
            'key' => 'AA',
        ]];

        try {
            self::assertNull(CountryGeometryCache::read($directory, 'signature'));
            CountryGeometryCache::write($directory, 'signature', $countries);
            $cached = CountryGeometryCache::read($directory, 'signature');
            self::assertSame('Example', $cached[0]['location']->country);
            self::assertTrue($cached[0]['area']->contains(20, 10));
            self::assertFalse($cached[0]['area']->contains(0, 0));
            self::assertNull(CountryGeometryCache::read($directory, 'other-signature'));

            $path = glob($directory.'/*')[0];
            $contents = file_get_contents($path);
            $contents[mb_strlen($contents, '8bit') - 1] = 'X';
            file_put_contents($path, $contents);
            self::assertNull(CountryGeometryCache::read($directory, 'signature'));
        } finally {
            foreach (glob($directory.'/*') as $path) {
                unlink($path);
            }

            rmdir($directory);
        }
    }

    private function ring(float $longitude, float $latitude, float $radius, int $positions): array
    {
        $ring = [];

        for ($i = 0; $i < $positions; $i++) {
            $angle = $i / $positions * 2 * M_PI;
            $distance = $radius * (1 + sin($angle * 9) / 10);
            $ring[] = [$longitude + cos($angle) * $distance, $latitude + sin($angle) * $distance];
        }

        $ring[] = $ring[0];

        return $ring;
    }
}
