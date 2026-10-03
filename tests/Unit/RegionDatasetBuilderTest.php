<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Geocoding\GeoJsonFeatureStream;
use App\Services\Geocoding\RegionDatasetBuilder;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RegionDatasetBuilderTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/rodnik-region-dataset-'.bin2hex(random_bytes(8));
        mkdir($this->directory);
        file_put_contents($this->directory.'/admin1.txt', "AA.01\tFirst\tFirst\t101\nAA.02\tSecond\tSecond\t102\n");
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*') as $path) {
            unlink($path);
        }

        rmdir($this->directory);
        parent::tearDown();
    }

    public function test_current_codes_join_by_id_and_empty_or_ambiguous_ne_identifiers_stay_separate(): void
    {
        $features = [
            $this->feature('NE-FIRST', 102, 'AA.01', 'Correct second'),
            $this->feature('NE-EMPTY', -99, 'AA.', 'No mapping'),
            $this->feature('NE-THIRD', 101, 'AA.01', 'Conflicting third'),
            $this->feature('NE-FOURTH', 101, 'AA.01', 'Conflicting fourth'),
        ];
        $this->source($features);
        $result = (new RegionDatasetBuilder)->normalize($this->directory.'/input.json', $this->directory.'/output.json', $this->directory.'/admin1.txt');
        $rows = iterator_to_array(GeoJsonFeatureStream::features($this->directory.'/output.json'));

        self::assertSame(4, $result['regions']);
        self::assertSame(2, $result['ambiguous_geonames_ids_cleared']);
        self::assertSame('geonames-admin1:AA.02', $rows[0]['properties']['source_key']);
        self::assertSame('02', $rows[0]['properties']['admin1_code']);
        self::assertSame(102, $rows[0]['properties']['geonames_id']);
        self::assertSame('natural-earth-admin1:NE-EMPTY', $rows[1]['properties']['source_key']);
        self::assertNull($rows[1]['properties']['admin1_code']);
        self::assertNull($rows[2]['properties']['geonames_id']);
        self::assertSame('natural-earth-admin1:NE-THIRD', $rows[2]['properties']['source_key']);
        self::assertNull($rows[3]['properties']['geonames_id']);
        self::assertSame('natural-earth-admin1:NE-FOURTH', $rows[3]['properties']['source_key']);
    }

    public function test_normalized_custom_identities_with_conflicting_geonames_ids_are_rejected(): void
    {
        $first = $this->feature('NE-FIRST', 101, 'AA.01', 'First');
        $first['properties'] = ['country_code' => 'AA', 'source_key' => 'custom:first', 'geonames_id' => 101, 'name' => 'First'];
        $second = $first;
        $second['properties']['source_key'] = 'custom:second';
        $this->source([$first, $second]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('conflicting polygon identities');
        (new RegionDatasetBuilder)->normalize($this->directory.'/input.json', $this->directory.'/output.json', $this->directory.'/admin1.txt');
    }

    private function source(array $features): void
    {
        file_put_contents($this->directory.'/input.json', json_encode(['type' => 'FeatureCollection', 'features' => $features]));
    }

    private function feature(string $code, int $id, string $adminCode, string $name): array
    {
        return [
            'type' => 'Feature',
            'properties' => ['adm1_code' => $code, 'gn_id' => $id, 'gn_a1_code' => $adminCode, 'iso_a2' => 'AA', 'name' => $name],
            'geometry' => ['type' => 'Polygon', 'coordinates' => [[[0, 0], [1, 0], [1, 1], [0, 1], [0, 0]]]],
        ];
    }
}
