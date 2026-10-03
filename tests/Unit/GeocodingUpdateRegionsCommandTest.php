<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\CreatesApplication;

final class GeocodingUpdateRegionsCommandTest extends TestCase
{
    use CreatesApplication;

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/rodnik-region-command-'.bin2hex(random_bytes(8));
        File::ensureDirectoryExists($this->directory);
        $this->app->useStoragePath($this->directory.'/storage');
        config()->set('geocoding.index_cache_path', $this->directory.'/cache');
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);
        parent::tearDown();
    }

    public function test_valid_custom_region_replacement_records_hash_and_warms_partitions(): void
    {
        $source = $this->source();
        $this->artisan('geocoding:update-regions', ['--source' => $source, '--directory' => $this->directory.'/output'])->assertSuccessful();
        $manifest = json_decode(file_get_contents($this->directory.'/output/regions.manifest.json'), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(hash_file('sha256', $source), $manifest['source_sha256']);
        self::assertSame(hash_file('sha256', $this->directory.'/output/regions.geojson'), $manifest['output_sha256']);
        self::assertSame(1, $manifest['regions']);
        self::assertNotEmpty(glob($this->directory.'/cache/*-AA.bin'));
    }

    public function test_malformed_custom_source_cannot_replace_previous_geometry_or_manifest(): void
    {
        File::ensureDirectoryExists($this->directory.'/output');
        file_put_contents($this->directory.'/output/regions.geojson', 'Existing geography');
        file_put_contents($this->directory.'/output/regions.manifest.json', 'Existing provenance');
        $source = $this->source();
        file_put_contents($source, ' trailing malformed data', FILE_APPEND);
        $this->artisan('geocoding:update-regions', ['--source' => $source, '--directory' => $this->directory.'/output'])->assertFailed();

        self::assertSame('Existing geography', file_get_contents($this->directory.'/output/regions.geojson'));
        self::assertSame('Existing provenance', file_get_contents($this->directory.'/output/regions.manifest.json'));
        self::assertSame([], glob($this->directory.'/output/.regions-*'));
    }

    private function source(): string
    {
        $path = $this->directory.'/source.geojson';
        file_put_contents($path, json_encode([
            'type' => 'FeatureCollection',
            'features' => [[
                'type' => 'Feature',
                'properties' => ['country_code' => 'AA', 'source_key' => 'custom:first', 'name' => 'First', 'name_ru' => 'Первый'],
                'geometry' => ['type' => 'Polygon', 'coordinates' => [[[0, 0], [1, 0], [1, 1], [0, 1], [0, 0]]]],
            ]],
        ], JSON_THROW_ON_ERROR));

        return $path;
    }
}
