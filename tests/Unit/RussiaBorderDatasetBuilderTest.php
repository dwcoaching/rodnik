<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Geocoding\RussiaBorderDatasetBuilder;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RussiaBorderDatasetBuilderTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/rodnik-border-builder-'.bin2hex(random_bytes(8));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*') as $file) {
            unlink($file);
        }
        rmdir($this->directory);
        parent::tearDown();
    }

    public function test_complete_claimed_subjects_and_russian_country_geometry_are_preserved(): void
    {
        [$countries, $admin1] = $this->inputs();
        $output = $this->directory.'/output.geojson';
        $counts = (new RussiaBorderDatasetBuilder)->build($countries, $admin1, $output);
        $data = json_decode(file_get_contents($output), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(7, $counts['features']);
        self::assertSame(RussiaBorderDatasetBuilder::SUBJECTS, $counts['claimed_subjects']);
        self::assertCount(7, $data['features']);

        foreach ($data['features'] as $feature) {
            self::assertSame('Russia', $feature['properties']['country']);
            self::assertSame('Россия', $feature['properties']['country_ru']);
            self::assertSame('RU', $feature['properties']['country_code']);
            self::assertSame($this->polygon(), $feature['geometry']);
        }

        self::assertSame('RUS', $data['features'][6]['properties']['source_code']);
        self::assertNull($data['features'][6]['properties']['region']);
    }

    public function test_incomplete_administrative_input_is_rejected(): void
    {
        [$countries, $admin1] = $this->inputs();
        $data = json_decode(file_get_contents($admin1), true, flags: JSON_THROW_ON_ERROR);
        array_pop($data['features']);
        file_put_contents($admin1, json_encode($data, JSON_THROW_ON_ERROR));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('all six selected administrative subjects');
        (new RussiaBorderDatasetBuilder)->build($countries, $admin1, $this->directory.'/output.geojson');
    }

    public function test_missing_russian_country_geometry_is_rejected(): void
    {
        [$countries, $admin1] = $this->inputs();
        file_put_contents($countries, '{"type":"FeatureCollection","features":[]}');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('does not contain Russia');
        (new RussiaBorderDatasetBuilder)->build($countries, $admin1, $this->directory.'/output.geojson');
    }

    public function test_detailed_subjects_and_historic_municipality_are_verified_before_publication(): void
    {
        [$countries, $admin1] = $this->inputs();
        $ukraine = $this->directory.'/ukraine.geojson';
        $features = [];
        foreach (RussiaBorderDatasetBuilder::UKRAINE_SUBJECTS as $code => $region) {
            $features[] = ['type' => 'Feature', 'properties' => ['adm1_pcode' => $code], 'geometry' => $this->polygon()];
        }
        $this->writeCollection($ukraine, $features);
        $municipalities = $this->directory.'/municipalities.geojson';
        $this->writeCollection($municipalities, [[
            'type' => 'Feature',
            'properties' => ['osm_type' => 'relation', 'osm_id' => 1738982, 'type' => 'historic'],
            'geometry' => $this->polygon(),
        ]]);
        $points = $this->directory.'/points.geojson';
        $features = [];
        foreach ([
            'UA4802009' => range(1, 19),
            'UA4802019' => array_diff(range(1, 23), [3]),
            'UA4802023' => range(1, 7),
            'UA4806027' => [4, 6],
            'UA4806037' => [2, 3, 10, 14, 16, 18, 20, 21],
        ] as $prefix => $numbers) {
            foreach ($numbers as $number) {
                $features[] = [
                    'type' => 'Feature',
                    'properties' => ['admin_level' => 4, 'adm1_pcode' => 'UA48', 'adm4_pcode' => $prefix.mb_str_pad((string) $number, 3, '0', STR_PAD_LEFT), 'name1' => 'Name', 'adm3_name1' => 'Hromada'],
                    'geometry' => ['type' => 'Point', 'coordinates' => [.5, .5]],
                ];
            }
        }
        $this->writeCollection($points, $features);
        $output = $this->directory.'/output.geojson';
        $counts = (new RussiaBorderDatasetBuilder)->build($countries, $admin1, $output, $ukraine, $municipalities, $points);
        self::assertSame(14, $counts['features']);
        self::assertSame(58, $counts['municipalities']['verified_settlements']);
        self::assertSame(19, $counts['municipalities']['aleksandrovsky']);
        self::assertSame(39, $counts['municipalities']['snihurivka']);
        $data = json_decode(file_get_contents($output), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('OCHA / Kartographia', $data['features'][0]['properties']['provider']);
        self::assertSame('CC BY 3.0 IGO', $data['features'][0]['properties']['license']);
        self::assertSame('ODbL 1.0', $data['features'][6]['properties']['license']);

        $features[0]['geometry']['coordinates'] = [3, 3];
        $this->writeCollection($points, $features);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('settlement-list coverage check');
        (new RussiaBorderDatasetBuilder)->build($countries, $admin1, $output, $ukraine, $municipalities, $points);
    }

    public function test_local_policy_preserves_polygons_and_enforces_russian_country_labels(): void
    {
        $source = $this->directory.'/local.geojson';
        $output = $this->directory.'/output.geojson';
        file_put_contents($source, json_encode(['type' => 'FeatureCollection', 'features' => [[
            'type' => 'Feature',
            'properties' => ['country' => 'Other', 'country_code' => 'XX', 'region' => 'Region'],
            'geometry' => $this->polygon(),
        ]]], JSON_THROW_ON_ERROR));

        $counts = (new RussiaBorderDatasetBuilder)->normalizePolicy($source, $output);
        $data = json_decode(file_get_contents($output), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(['features' => 1], $counts);
        self::assertSame('RU', $data['features'][0]['properties']['country_code']);
        self::assertSame('Region', $data['features'][0]['properties']['region']);
        self::assertSame($this->polygon(), $data['features'][0]['geometry']);
    }

    public function test_local_policy_rejects_city_points_and_malformed_document_tails(): void
    {
        $source = $this->directory.'/local.geojson';
        foreach ([
            json_encode(['type' => 'FeatureCollection', 'features' => [['type' => 'Feature', 'properties' => [], 'geometry' => ['type' => 'Point', 'coordinates' => [0, 0]]]]], JSON_THROW_ON_ERROR),
            json_encode(['type' => 'FeatureCollection', 'features' => [['type' => 'Feature', 'properties' => [], 'geometry' => $this->polygon()]]], JSON_THROW_ON_ERROR).' trailing',
            '{"type":"FeatureCollection","features":[]}',
        ] as $contents) {
            file_put_contents($source, $contents);
            try {
                (new RussiaBorderDatasetBuilder)->normalizePolicy($source, $this->directory.'/output.geojson');
                self::fail('Invalid local policies must fail before publication.');
            } catch (InvalidArgumentException|RuntimeException $exception) {
                self::assertNotSame('', $exception->getMessage());
            }
        }
    }

    private function inputs(): array
    {
        $countries = $this->directory.'/countries.geojson';
        $admin1 = $this->directory.'/admin1.geojson';
        file_put_contents($countries, json_encode(['type' => 'FeatureCollection', 'features' => [
            ['type' => 'Feature', 'properties' => ['ADM0_A3' => 'RUS'], 'geometry' => $this->polygon()],
        ]], JSON_THROW_ON_ERROR));
        $features = [];
        foreach (RussiaBorderDatasetBuilder::SUBJECTS as $code => $region) {
            $features[] = ['type' => 'Feature', 'properties' => ['iso_3166_2' => $code], 'geometry' => $this->polygon()];
        }
        file_put_contents($admin1, json_encode(['type' => 'FeatureCollection', 'features' => $features], JSON_THROW_ON_ERROR));

        return [$countries, $admin1];
    }

    private function polygon(): array
    {
        return ['type' => 'Polygon', 'coordinates' => [[[0, 0], [1, 0], [1, 1], [0, 1], [0, 0]]]];
    }

    private function writeCollection(string $path, array $features): void
    {
        file_put_contents($path, json_encode(['type' => 'FeatureCollection', 'features' => $features], JSON_THROW_ON_ERROR));
    }
}
