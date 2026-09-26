<?php

declare(strict_types=1);

use App\Rules\GeoJsonTrackRule;
use App\Support\TrackGeometry;
use Symfony\Component\Process\Process;

test('track summaries preserve bounds and distances without joining separate segments', function (): void {
    $track = json_decode(json_encode(['type' => 'FeatureCollection', 'features' => [
        ['geometry' => ['type' => 'LineString', 'coordinates' => [[0, 0], [1, 0]]]],
        ['geometry' => ['type' => 'MultiLineString', 'coordinates' => [[[10, 0], [11, 0]], [[20, 0], [21, 0]]]]],
        ['geometry' => ['type' => 'Point', 'coordinates' => [90, 45]]],
    ]], JSON_THROW_ON_ERROR), false, 32, JSON_THROW_ON_ERROR);

    expect(TrackGeometry::summary($track))->toBe([
        'distance_km' => 333.58,
        'preview' => [[0.0, 0.0], [1.0, 0.0], [10.0, 0.0], [11.0, 0.0], [20.0, 0.0], [21.0, 0.0], [90.0, 45.0]],
        'bbox' => [0.0, 0.0, 90.0, 45.0],
    ]);
});

test('track previews sample the same positions and always include the final point', function (int $count, array $indices): void {
    $coordinates = array_map(fn (int $index): array => [$index / 1000, 0], range(0, $count - 1));
    $track = (object) ['features' => [(object) ['geometry' => (object) ['type' => 'LineString', 'coordinates' => $coordinates]]]];

    expect(TrackGeometry::summary($track)['preview'])->toBe(array_map(fn (int $index): array => [(float) ($index / 1000), 0.0], $indices));
})->with([
    'below sampling threshold' => [99, range(0, 98)],
    'at sampling threshold' => [100, range(0, 99)],
    'just above threshold' => [101, range(0, 100, 2)],
    'final point between samples' => [200, [...range(0, 198, 2), 199]],
    'larger step with final point' => [201, [...range(0, 198, 3), 200]],
]);

test('a maximum-size accepted track summary fits a 128 MiB worker with existing application memory', function (): void {
    $serialized = '{"type":"FeatureCollection","features":[{"type":"Feature","properties":null,"geometry":{"type":"LineString","coordinates":['
        .str_repeat('[0,0],', GeoJsonTrackRule::MAX_COORDINATES - 1).'[0,0]]}}]}';
    $process = new Process([PHP_BINARY, '-d', 'memory_limit=128M', '-r', <<<'PHP'
        require 'vendor/autoload.php';

        $applicationMemory = str_repeat('x', 32 * 1024 * 1024);
        $serialized = stream_get_contents(STDIN);
        $errors = [];
        (new App\Rules\GeoJsonTrackRule)->validate('track', $serialized, function (string $message) use (&$errors): void {
            $errors[] = $message;
        });
        $track = json_decode($serialized, false, 32, JSON_THROW_ON_ERROR);
        $summary = App\Support\TrackGeometry::summary($track);
        echo json_encode(['errors' => $errors, 'summary' => $summary, 'application_bytes' => strlen($applicationMemory)], JSON_THROW_ON_ERROR);
        PHP,
    ], dirname(__DIR__, 2));
    $process->setInput($serialized);
    $process->mustRun();
    $result = json_decode($process->getOutput(), true, 32, JSON_THROW_ON_ERROR);

    expect($result['errors'])->toBeEmpty()
        ->and($result['application_bytes'])->toBe(32 * 1024 * 1024)
        ->and($result['summary']['distance_km'])->toBe(0)
        ->and($result['summary']['preview'])->toHaveCount(101)
        ->and($result['summary']['bbox'])->toBe([0, 0, 0, 0]);
});
