<?php

declare(strict_types=1);

use App\Library\TrackPolygonArea;
use Symfony\Component\Process\Process;

test('track polygons match Turf including exterior and hole boundaries', function (float $longitude, float $latitude, bool $inside) {
    $area = TrackPolygonArea::fromArray([
        'type' => 'Polygon',
        'coordinates' => [
            [[0, 0], [10, 0], [10, 10], [0, 10], [0, 0]],
            [[3, 3], [7, 3], [7, 7], [3, 7], [3, 3]],
        ],
    ]);

    expect($area->contains($longitude, $latitude))->toBe($inside);
})->with([
    'inside' => [2, 2, true],
    'outside' => [12, 2, false],
    'exterior edge' => [0, 5, true],
    'exterior vertex' => [10, 10, true],
    'hole interior' => [5, 5, false],
    'hole edge' => [3, 5, true],
    'hole vertex' => [3, 3, true],
    'immediately inside hole' => [3.000000000001, 5, false],
    'immediately outside shell' => [-0.000000000001, 5, false],
]);

test('track multipolygons evaluate every polygon with its holes', function () {
    $area = TrackPolygonArea::fromArray([
        'type' => 'MultiPolygon',
        'coordinates' => [
            [
                [[0, 0], [10, 0], [10, 10], [0, 10], [0, 0]],
                [[3, 3], [7, 3], [7, 7], [3, 7], [3, 3]],
            ],
            [[[20, 20], [22, 20], [22, 22], [20, 22], [20, 20]]],
        ],
    ]);

    expect($area->contains(2, 2))->toBeTrue()
        ->and($area->contains(5, 5))->toBeFalse()
        ->and($area->contains(21, 21))->toBeTrue()
        ->and($area->contains(20, 21))->toBeTrue()
        ->and($area->contains(15, 15))->toBeFalse();
});

test('track geometry rejects empty malformed and topologically invalid polygons', function (array $geometry) {
    expect(fn () => TrackPolygonArea::fromArray($geometry))->toThrow(InvalidArgumentException::class);
})->with([
    'missing coordinates' => [[]],
    'non-polygon' => [['type' => 'LineString', 'coordinates' => [[0, 0], [1, 1]]]],
    'empty polygon' => [['type' => 'Polygon', 'coordinates' => []]],
    'empty multipolygon' => [['type' => 'MultiPolygon', 'coordinates' => []]],
    'empty member' => [['type' => 'MultiPolygon', 'coordinates' => [[]]]],
    'empty ring' => [['type' => 'Polygon', 'coordinates' => [[]]]],
    'non-list coordinates' => [['type' => 'Polygon', 'coordinates' => ['ring' => [[0, 0], [1, 0], [0, 1], [0, 0]]]]],
    'short ring' => [['type' => 'Polygon', 'coordinates' => [[[0, 0], [1, 0], [0, 0]]]]],
    'unclosed ring' => [['type' => 'Polygon', 'coordinates' => [[[0, 0], [1, 0], [1, 1], [0, 1]]]]],
    'invalid position' => [['type' => 'Polygon', 'coordinates' => [[[0], [1, 0], [0, 1], [0, 0]]]]],
    'string coordinate' => [['type' => 'Polygon', 'coordinates' => [[['0', 0], [1, 0], [0, 1], ['0', 0]]]]],
    'non-finite coordinate' => [['type' => 'Polygon', 'coordinates' => [[[0, 0], [INF, 0], [0, 1], [0, 0]]]]],
    'longitude outside range' => [['type' => 'Polygon', 'coordinates' => [[[0, 0], [181, 0], [0, 1], [0, 0]]]]],
    'self intersection' => [['type' => 'Polygon', 'coordinates' => [[[0, 0], [2, 2], [2, 0], [0, 2], [0, 0]]]]],
    'zero area' => [['type' => 'Polygon', 'coordinates' => [[[0, 0], [1, 0], [2, 0], [0, 0]]]]],
    'hole outside shell' => [[
        'type' => 'Polygon',
        'coordinates' => [
            [[0, 0], [10, 0], [10, 10], [0, 10], [0, 0]],
            [[13, 13], [17, 13], [17, 17], [13, 17], [13, 13]],
        ],
    ]],
    'overlapping multipolygon members' => [[
        'type' => 'MultiPolygon',
        'coordinates' => [
            [[[0, 0], [10, 0], [10, 10], [0, 10], [0, 0]]],
            [[[5, 5], [15, 5], [15, 15], [5, 15], [5, 5]]],
        ],
    ]],
]);

test('track geometry rejects non-finite candidate coordinates', function (float $longitude, float $latitude) {
    $area = TrackPolygonArea::fromArray([
        'type' => 'Polygon',
        'coordinates' => [[[0, 0], [1, 0], [0, 1], [0, 0]]],
    ]);

    expect(fn () => $area->contains($longitude, $latitude))->toThrow(InvalidArgumentException::class);
})->with([
    'infinite longitude' => [INF, 0],
    'not a number latitude' => [0, NAN],
]);

test('native track geometry evaluates a long narrow corridor with 5579 vertices', function () {
    $upperEdge = [];
    $lowerEdge = [];
    $centers = [];

    for ($index = 0; $index < 2789; $index++) {
        $fraction = $index / 2788;
        $longitude = -84 + 17 * $fraction;
        $latitude = 35 + 10 * $fraction + 0.1 * sin($fraction * 300);
        $centers[] = [$longitude, $latitude];
        $upperEdge[] = [$longitude, $latitude + 0.003];
        $lowerEdge[] = [$longitude, $latitude - 0.003];
    }

    $ring = [...$upperEdge, ...array_reverse($lowerEdge), $upperEdge[0]];
    $area = TrackPolygonArea::fromArray(['type' => 'Polygon', 'coordinates' => [$ring]]);

    expect($ring)->toHaveCount(5579);

    for ($index = 0; $index < count($centers); $index += 28) {
        [$longitude, $latitude] = $centers[$index];

        expect($area->contains($longitude, $latitude))->toBeTrue()
            ->and($area->contains($longitude, $latitude + 0.02))->toBeFalse();
    }
});

test('track filtering fails explicitly when GEOS is unavailable', function () {
    $classFile = dirname(__DIR__, 2).'/app/Library/TrackPolygonArea.php';
    $process = new Process([PHP_BINARY, '-n', '-r', <<<'PHP'
        require $argv[1];

        try {
            App\Library\TrackPolygonArea::fromArray([
                'type' => 'Polygon',
                'coordinates' => [[[0, 0], [1, 0], [0, 1], [0, 0]]],
            ]);
            exit(1);
        } catch (RuntimeException $exception) {
            echo $exception->getMessage();
        }
        PHP, $classFile]);
    $process->run();

    expect($process->isSuccessful())->toBeTrue()
        ->and($process->getOutput())->toContain('The GEOS extension is required');
});
