<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Geocoding\GeoJsonFeatureStream;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class GeoJsonFeatureStreamTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();
        $this->path = tempnam(sys_get_temp_dir(), 'rodnik-json-stream-');
    }

    protected function tearDown(): void
    {
        unlink($this->path);
        parent::tearDown();
    }

    public function test_features_stream_across_buffer_boundaries_with_reordered_root_keys(): void
    {
        $features = [[
            'type' => 'Feature',
            'properties' => ['name' => 'Quoted "name" with slash \\ and Unicode Я', 'padding' => str_repeat('x', 70000)],
            'geometry' => ['type' => 'Point', 'coordinates' => [0, 0]],
        ], [
            'type' => 'Feature',
            'properties' => ['name' => 'Second'],
            'geometry' => ['type' => 'Point', 'coordinates' => [1, 1]],
        ]];
        file_put_contents($this->path, json_encode([
            'description' => 'Brackets in a string: { [ ] }',
            'features' => $features,
            'type' => 'FeatureCollection',
            'bbox' => [0, 0, 1, 1],
        ], JSON_THROW_ON_ERROR));

        self::assertSame($features, iterator_to_array(GeoJsonFeatureStream::features($this->path)));
    }

    public function test_complete_document_and_every_feature_are_validated(): void
    {
        foreach ([
            '{"type":"FeatureCollection","features":[{}]} trailing',
            '{"type":"FeatureCollection","features":[{},]}',
            '{"type":"FeatureCollection","features":[{}]',
            '{"type":"Other","features":[{}]}',
            '{"type":"FeatureCollection","features":[1]}',
            '{"type":"FeatureCollection","features":[],"features":[]}',
            '{"type":"FeatureCollection","features":[{"value":"unclosed}]}',
        ] as $contents) {
            file_put_contents($this->path, $contents);

            try {
                iterator_to_array(GeoJsonFeatureStream::features($this->path));
                self::fail('Malformed FeatureCollections must fail before publication.');
            } catch (RuntimeException $exception) {
                self::assertNotSame('', $exception->getMessage());
            }
        }
    }

    public function test_empty_valid_feature_collection_streams_no_features(): void
    {
        file_put_contents($this->path, '{"features":[],"type":"FeatureCollection"}');

        self::assertSame([], iterator_to_array(GeoJsonFeatureStream::features($this->path)));
    }
}
