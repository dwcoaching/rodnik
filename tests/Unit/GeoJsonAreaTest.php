<?php

declare(strict_types=1);

use App\Library\GeoJsonArea;

test('it checks points inside polygons and holes', function () {
    $area = GeoJsonArea::fromArray([
        'type' => 'Polygon',
        'coordinates' => [[
            [0, 0],
            [10, 0],
            [10, 10],
            [0, 10],
            [0, 0],
        ], [
            [3, 3],
            [7, 3],
            [7, 7],
            [3, 7],
            [3, 3],
        ]],
    ]);

    expect($area->contains(2, 2))->toBeTrue();
    expect($area->contains(3, 3))->toBeFalse();
    expect($area->contains(5, 5))->toBeFalse();
    expect($area->contains(12, 2))->toBeFalse();
});

test('it checks points inside multipolygons', function () {
    $area = GeoJsonArea::fromArray([
        'type' => 'MultiPolygon',
        'coordinates' => [[[
            [0, 0],
            [2, 0],
            [2, 2],
            [0, 2],
            [0, 0],
        ]], [[
            [10, 10],
            [12, 10],
            [12, 12],
            [10, 12],
            [10, 10],
        ]]],
    ]);

    expect($area->contains(1, 1))->toBeTrue();
    expect($area->contains(11, 11))->toBeTrue();
    expect($area->contains(5, 5))->toBeFalse();
});

test('it checks points inside feature collections', function () {
    $area = GeoJsonArea::fromArray([
        'type' => 'FeatureCollection',
        'features' => [[
            'type' => 'Feature',
            'properties' => [],
            'geometry' => [
                'type' => 'Polygon',
                'coordinates' => [[
                    [37.0, 57.0],
                    [38.0, 57.0],
                    [38.0, 58.0],
                    [37.0, 58.0],
                    [37.0, 57.0],
                ]],
            ],
        ]],
    ]);

    expect($area->contains(37.5, 57.5))->toBeTrue();
    expect($area->contains(36.5, 57.5))->toBeFalse();
});
