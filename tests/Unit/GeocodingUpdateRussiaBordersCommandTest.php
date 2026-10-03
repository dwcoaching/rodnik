<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\CreatesApplication;

final class GeocodingUpdateRussiaBordersCommandTest extends TestCase
{
    use CreatesApplication;

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/rodnik-border-command-'.bin2hex(random_bytes(8));
        File::ensureDirectoryExists($this->directory);
        $this->app->useStoragePath($this->directory.'/storage');
        config()->set('geocoding.russia_border_path', $this->directory.'/installed/borders.geojson');
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);
        parent::tearDown();
    }

    public function test_local_replacement_uses_configured_destination_and_records_the_exact_source(): void
    {
        $source = $this->directory.'/replacement.geojson';
        $this->writePolicy($source);

        $this->artisan('geocoding:update-russia-borders', ['--source' => $source])->assertSuccessful();

        $output = $this->directory.'/installed/borders.geojson';
        $data = json_decode(file_get_contents($output), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('Russia', $data['features'][0]['properties']['country']);
        self::assertSame('Россия', $data['features'][0]['properties']['country_ru']);
        self::assertSame('RU', $data['features'][0]['properties']['country_code']);
        self::assertSame('Region', $data['features'][0]['properties']['region']);
        $manifest = json_decode(file_get_contents($this->directory.'/installed/borders.manifest.json'), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(hash_file('sha256', $source), $manifest['inputs']['local_policy']['sha256']);
        self::assertSame(hash_file('sha256', $output), $manifest['output']['sha256']);
        self::assertFalse($manifest['official_geometry']);
        self::assertSame(1, $manifest['counts']['features']);
    }

    public function test_malformed_local_replacement_cannot_replace_a_good_existing_policy(): void
    {
        $output = $this->directory.'/installed/borders.geojson';
        File::ensureDirectoryExists(dirname($output));
        $this->writePolicy($output);
        $original = file_get_contents($output);
        $manifest = $this->directory.'/installed/borders.manifest.json';
        file_put_contents($manifest, '{"previous":"manifest"}');
        $source = $this->directory.'/replacement.geojson';
        $this->writePolicy($source);
        file_put_contents($source, ' trailing malformed data', FILE_APPEND);

        $this->artisan('geocoding:update-russia-borders', ['--source' => $source])->assertFailed();

        self::assertSame($original, file_get_contents($output));
        self::assertSame('{"previous":"manifest"}', file_get_contents($manifest));
        self::assertSame([], glob($this->directory.'/installed/.russia-borders-*'));
    }

    private function writePolicy(string $path): void
    {
        file_put_contents($path, json_encode([
            'type' => 'FeatureCollection',
            'features' => [[
                'type' => 'Feature',
                'properties' => ['country' => 'Original', 'country_code' => 'XX', 'region' => ' Region '],
                'geometry' => ['type' => 'Polygon', 'coordinates' => [[[0, 0], [5, 0], [5, 5], [0, 5], [0, 0]]]],
            ]],
        ], JSON_THROW_ON_ERROR));
    }
}
