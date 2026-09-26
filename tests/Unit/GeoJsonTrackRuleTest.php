<?php

declare(strict_types=1);

use App\Rules\GeoJsonTrackRule;
use Symfony\Component\Process\Process;

test('track positions allow missing elevation only when a numeric timestamp follows it', function (array $position, bool $accepted) {
    $errors = [];
    $serialized = json_encode([
        'type' => 'FeatureCollection',
        'features' => [[
            'type' => 'Feature', 'properties' => null,
            'geometry' => ['type' => 'Point', 'coordinates' => $position],
        ]],
    ], JSON_THROW_ON_ERROR);

    (new GeoJsonTrackRule)->validate('track', $serialized, function (string $message) use (&$errors): void {
        $errors[] = $message;
    });

    expect($errors === [])->toBe($accepted);
})->with([
    'time without elevation' => [[37, 55, null, 1750000000], true],
    'zero time without elevation' => [[37, 55, null, 0], true],
    'null longitude' => [[null, 55, null, 1750000000], false],
    'null latitude' => [[37, null, null, 1750000000], false],
    'null elevation without time' => [[37, 55, null], false],
    'null time' => [[37, 55, 100, null], false],
    'null elevation and time' => [[37, 55, null, null], false],
    'text time' => [[37, 55, null, '2026-09-26'], false],
]);

test('track properties cannot override geometry or supply nontext labels', function (array $properties, bool $accepted) {
    $errors = [];
    $serialized = json_encode([
        'type' => 'FeatureCollection',
        'features' => [[
            'type' => 'Feature',
            'properties' => $properties,
            'geometry' => ['type' => 'Point', 'coordinates' => [37, 55]],
        ]],
    ], JSON_THROW_ON_ERROR);

    (new GeoJsonTrackRule)->validate('track', $serialized, function (string $message) use (&$errors): void {
        $errors[] = $message;
    });

    expect($errors === [])->toBe($accepted);
})->with([
    'numeric name' => [['name' => 123], false],
    'boolean name' => [['name' => true], false],
    'numeric description' => [['desc' => 123], false],
    'boolean description' => [['desc' => false], false],
    'geometry override' => [['geometry' => 'overridden'], false],
    'null geometry override' => [['geometry' => null], false],
    'text labels' => [['name' => 'Route', 'desc' => 'Water'], true],
    'null labels' => [['name' => null, 'desc' => null], true],
    'unrelated scalar metadata' => [['elevation' => 123, 'visited' => true], true],
]);

test('track validation stays within a 128 MiB worker memory limit', function (string $serialized, bool $accepted) {
    expect(mb_strlen($serialized, '8bit'))->toBeLessThanOrEqual(GeoJsonTrackRule::MAX_BYTES);

    $process = new Process([PHP_BINARY, '-d', 'memory_limit=128M', '-r', <<<'PHP'
        require 'vendor/autoload.php';

        $errors = [];
        $track = stream_get_contents(STDIN);

        (new App\Rules\GeoJsonTrackRule)->validate('track', $track, function (string $message) use (&$errors): void {
            $errors[] = $message;
        });

        echo json_encode(['errors' => $errors, 'peak_memory' => memory_get_peak_usage(true)], JSON_THROW_ON_ERROR);
        PHP,
    ], dirname(__DIR__, 2));
    $process->setInput($serialized);
    $process->mustRun();

    $result = json_decode($process->getOutput(), true, 32, JSON_THROW_ON_ERROR);

    if ($accepted) {
        expect($result['errors'])->toBeEmpty();
    } else {
        expect($result['errors'])->toHaveCount(1)
            ->and($result['errors'][0])->toContain('too complex')
            ->and($result['peak_memory'])->toBeLessThan(32 * 1024 * 1024);
    }
})->with([
    'one million compact coordinates' => [
        fn (): string => '{"type":"FeatureCollection","features":[{"type":"Feature","properties":null,"geometry":{"type":"LineString","coordinates":['.str_repeat('[0,0],', 999_999).'[0,0]]}}]}',
        false,
    ],
    'multibyte metadata before compact coordinates' => [
        fn (): string => '{"type":"FeatureCollection","features":[{"type":"Feature","properties":{"name":"'.str_repeat('💧', 1_500_000).'"},"geometry":{"type":"LineString","coordinates":['.str_repeat('[0,0],', 599_999).'[0,0]]}}]}',
        false,
    ],
    'many scalar metadata properties' => [
        function (): string {
            $properties = '';

            for ($index = 0; $index < 800_000; $index++) {
                $properties .= '"p'.dechex($index).'":0,';
            }

            return '{"type":"FeatureCollection","features":[{"type":"Feature","properties":{'.$properties.'"last":0},"geometry":{"type":"Point","coordinates":[0,0]}}]}';
        },
        false,
    ],
    'objects outside the supported schema' => [
        fn (): string => '{"foreign":['.str_repeat('{},', 399_999).'{}]}',
        false,
    ],
    'the coordinate limit with elevation and time' => [
        fn (): string => '{"type":"FeatureCollection","features":[{"type":"Feature","properties":null,"geometry":{"type":"LineString","coordinates":['.str_repeat('[0,0,0,1750000000],', GeoJsonTrackRule::MAX_COORDINATES - 1).'[0,0,0,1750000000]]}}]}',
        true,
    ],
    'structural characters and escapes inside metadata strings' => [
        fn (): string => json_encode([
            'type' => 'FeatureCollection',
            'features' => [[
                'type' => 'Feature',
                'properties' => ['description' => str_repeat('quoted " {"features": [[0, 0]], "path": "C:\\track\\"} ', 50_000)],
                'geometry' => ['type' => 'Point', 'coordinates' => [0, 0]],
            ]],
        ], JSON_THROW_ON_ERROR),
        true,
    ],
]);
