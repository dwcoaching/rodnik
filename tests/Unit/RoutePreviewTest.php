<?php

declare(strict_types=1);

use App\Support\Docs\RoutePreview;

// Expected paths were produced by routePreview() in resources/js/libraryPreview.js.
test('route previews match the browser library preview', function (array $points, string $path) {
    expect(RoutePreview::path($points))->toBe($path);
})->with([
    'trail' => [
        [[30.486169, 36.876888], [30.470292, 36.862523], [30.455859, 36.844309], [30.441736, 36.807554], [30.4391, 36.781875]],
        'M60.3,12.0 L53.4,19.9 L47.0,29.8 L40.8,50.0 L39.7,64.0',
    ],
    'antimeridian' => [[[179.9, -16.5], [-179.8, -16.4], [-179.5, -16.2]], 'M12.0,57.8 L50.0,44.6 L88.0,18.2'],
    'vertical line' => [[[0, 0], [0, 1]], 'M50.0,64.0 L50.0,12.0'],
    'single point' => [[[10, 10]], ''],
    'invalid points are skipped' => [[[10, 10], ['11', 11], [200, 5], [12, 95]], ''],
]);

test('route previews can sample long tracks evenly while keeping both ends', function () {
    $points = array_map(fn (int $index): array => [$index / 100, sin($index / 10)], range(0, 99));
    $sampled = array_map(fn (int $index): array => $points[$index], [0, 33, 66, 99]);

    expect(RoutePreview::path($points, 4))->toBe(RoutePreview::path($sampled))
        ->and(mb_substr_count(RoutePreview::path($points, 4), 'L'))->toBe(3)
        ->and(RoutePreview::path($points, 100))->toBe(RoutePreview::path($points))
        ->and(mb_substr_count(RoutePreview::path($points, 1), 'L'))->toBe(1);
});

test('route previews ignore malformed input', function (mixed $points) {
    expect(RoutePreview::path($points))->toBe('');
})->with([
    'null' => [null],
    'string' => ['M0,0'],
    'empty' => [[]],
    'nested garbage' => [[['a' => 1, 'b' => 2], [1], null]],
]);

test('map centers are formatted like the browser library', function (mixed $center, string $label) {
    expect(RoutePreview::coordinates($center))->toBe($label);
})->with([
    'north east' => [[37.6173, 55.7558], '55.76° N, 37.62° E'],
    'south west' => [[-70.25, -33.456], '33.46° S, 70.25° W'],
    'not finite' => [[1, NAN], ''],
    'not a list' => ['x', ''],
]);
