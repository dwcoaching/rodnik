<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Support\Facades\File;
use Tests\TestCase;
use ZipArchive;

final class GeocodingUpdateDatasetCommandTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/rodnik-dataset-command-'.bin2hex(random_bytes(8));
        File::ensureDirectoryExists($this->directory);
        $this->app->useStoragePath($this->directory.'/storage');
        config()->set('geocoding.reference_path', null);
        config()->set('geocoding.russia_border_path', null);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);
        parent::tearDown();
    }

    public function test_default_catalog_merges_country_villages_and_records_reproducible_inputs(): void
    {
        $inputs = $this->inputs();
        $this->artisan('geocoding:update-dataset', $inputs + [
            '--supplement-source' => [
                'RU='.$this->archive('RU.txt', 'RU', 2, 'Village'),
                'UA='.$this->archive('UA.txt', 'UA', 3, 'Village'),
            ],
        ])->assertSuccessful();

        $manifest = json_decode(file_get_contents($this->directory.'/output/manifest.json'), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('cities500', $manifest['cities']['dataset']);
        self::assertSame(['RU', 'UA'], $manifest['cities']['supplement_countries']);
        self::assertSame(3, $manifest['counts']['cities']);
        self::assertArrayHasKey('supplement_RU', $manifest['inputs']);
        self::assertSame(hash_file('sha256', $this->directory.'/RU.txt.zip'), $manifest['inputs']['supplement_RU']['sha256']);
        self::assertSame(3, count(file($this->directory.'/output/settlements.txt')));
    }

    public function test_explicit_empty_supplements_installs_only_the_selected_base_catalog(): void
    {
        $this->artisan('geocoding:update-dataset', $this->inputs() + ['--supplement-countries' => ''])->assertSuccessful();
        $manifest = json_decode(file_get_contents($this->directory.'/output/manifest.json'), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame([], $manifest['cities']['supplement_countries']);
        self::assertSame(1, $manifest['counts']['cities']);
    }

    public function test_a_broken_final_border_policy_cannot_replace_the_existing_dataset(): void
    {
        $inputs = $this->inputs();
        File::ensureDirectoryExists($this->directory.'/output');
        file_put_contents($this->directory.'/output/countries.geojson', 'existing country data');
        file_put_contents($this->directory.'/output/settlements.txt', 'existing settlement data');
        file_put_contents($this->directory.'/broken-border.geojson', '{"broken":');
        config()->set('geocoding.russia_border_path', $this->directory.'/broken-border.geojson');

        $this->artisan('geocoding:update-dataset', $inputs + ['--supplement-countries' => ''])->assertFailed();

        self::assertSame('existing country data', file_get_contents($this->directory.'/output/countries.geojson'));
        self::assertSame('existing settlement data', file_get_contents($this->directory.'/output/settlements.txt'));
        self::assertFileDoesNotExist($this->directory.'/output/manifest.json');
    }

    private function inputs(): array
    {
        file_put_contents($this->directory.'/countries.geojson', json_encode([
            'type' => 'FeatureCollection',
            'features' => [[
                'type' => 'Feature',
                'properties' => ['NAME_EN' => 'Example', 'ISO_A2_EH' => 'AA'],
                'geometry' => ['type' => 'Polygon', 'coordinates' => [[[0, 0], [5, 0], [5, 5], [0, 5], [0, 0]]]],
            ]],
        ], JSON_THROW_ON_ERROR));
        file_put_contents($this->directory.'/regions.txt', "AA.01\tProvince\tProvince\t123\n");

        return [
            '--directory' => $this->directory.'/output',
            '--countries-source' => $this->directory.'/countries.geojson',
            '--cities-source' => $this->archive('cities500.txt', 'AA', 1, 'City'),
            '--admin1-source' => $this->directory.'/regions.txt',
        ];
    }

    private function archive(string $member, string $country, int $id, string $name): string
    {
        $columns = array_fill(0, 19, '');
        foreach ([0 => (string) $id, 1 => $name, 4 => '1', 5 => '2', 6 => 'P', 7 => 'PPL', 8 => $country, 10 => '01', 14 => '0'] as $column => $value) {
            $columns[$column] = $value;
        }
        $path = $this->directory.'/'.$member.'.zip';
        $archive = new ZipArchive();
        $archive->open($path, ZipArchive::CREATE);
        $archive->addFromString($member, implode("\t", $columns)."\n");
        $archive->close();

        return $path;
    }
}
