<?php

declare(strict_types=1);

use App\Library\OverpassSeed;
use App\Models\OverpassAttempt;
use App\Models\OverpassBatch;
use App\Models\OverpassImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
});

/**
 * Every 1x1 degree cell of the globe must be covered exactly once. A gap leaves the batch
 * permanently short of full coverage, and an overlap pays for the same area twice.
 *
 * @param  array<int, array{latitude_from: int, latitude_to: int, longitude_from: int, longitude_to: int}>  $segments
 */
function tilingErrors(array $segments): array
{
    $seen = [];

    foreach ($segments as $segment) {
        for ($lon = $segment['longitude_from']; $lon < $segment['longitude_to']; $lon++) {
            for ($lat = $segment['latitude_from']; $lat < $segment['latitude_to']; $lat++) {
                $seen["{$lon},{$lat}"] = ($seen["{$lon},{$lat}"] ?? 0) + 1;
            }
        }
    }

    $gaps = 0;
    $overlaps = 0;

    for ($lon = -180; $lon < 180; $lon++) {
        for ($lat = -90; $lat < 90; $lat++) {
            $count = $seen["{$lon},{$lat}"] ?? 0;
            $count === 0 ? $gaps++ : ($count > 1 ? $overlaps++ : null);
        }
    }

    return ['gaps' => $gaps, 'overlaps' => $overlaps, 'cells' => count($seen)];
}

/**
 * A cost map where one column is expensive and the rest are nearly free.
 *
 * @return array<int, array<int, float>>
 */
function costMap(array $expensive = [], float $cheap = 0.1): array
{
    $costs = [];

    for ($lon = -180; $lon < 180; $lon++) {
        for ($lat = -90; $lat < 90; $lat++) {
            $costs[$lon][$lat] = $cheap;
        }
    }

    foreach ($expensive as $lon => $secondsPerCell) {
        for ($lat = -90; $lat < 90; $lat++) {
            $costs[$lon][$lat] = $secondsPerCell;
        }
    }

    return $costs;
}

test('the seed covers the globe exactly once with no gaps or overlaps', function () {
    $segments = OverpassSeed::segmentsFor(costMap([9 => 1.5, 8 => 1.2, -74 => 0.9]));

    expect(tilingErrors($segments))->toBe(['gaps' => 0, 'overlaps' => 0, 'cells' => 64800]);
});

test('the uniform fallback also covers the globe exactly once', function () {
    $segments = OverpassSeed::uniformSegments();

    expect($segments)->toHaveCount(360)
        ->and(tilingErrors($segments))->toBe(['gaps' => 0, 'overlaps' => 0, 'cells' => 64800]);
});

test('cheap columns merge into wider blocks', function () {
    // 0.1s per cell over 180 cells is 18s per column, so three fit under the 60s target.
    $segments = OverpassSeed::segmentsFor(costMap());

    $widths = array_map(fn ($s) => $s['longitude_to'] - $s['longitude_from'], $segments);

    expect(max($widths))->toBe(3)
        ->and(count($segments))->toBeLessThan(360)
        ->and(tilingErrors($segments))->toBe(['gaps' => 0, 'overlaps' => 0, 'cells' => 64800]);
});

test('an expensive column is split into latitude bands instead of merged', function () {
    // 1.5s per cell over 180 cells is 270s, which needs five bands to fit under 60s.
    $segments = OverpassSeed::segmentsFor(costMap([9 => 1.5]));

    $bands = array_values(array_filter($segments, fn ($s) => $s['longitude_from'] === 9));

    expect(count($bands))->toBeGreaterThanOrEqual(5)
        ->and(array_unique(array_map(fn ($s) => $s['longitude_to'] - $s['longitude_from'], $bands)))->toBe([1])
        ->and($bands[0]['latitude_from'])->toBe(-90)
        ->and(end($bands)['latitude_to'])->toBe(90);
});

test('latitude bands are contiguous and ordered', function () {
    $bands = OverpassSeed::latitudeBands(array_fill_keys(range(-90, 89), 1.0), 180.0);

    expect($bands[0][0])->toBe(-90)
        ->and(end($bands)[1])->toBe(90);

    foreach (array_slice($bands, 1) as $i => $band) {
        expect($band[0])->toBe($bands[$i][1]);
    }
});

test('a column with no measured cost is never split into empty bands', function () {
    expect(OverpassSeed::latitudeBands([], 0.0))->toBe([[-90, 90]]);
});

test('measured cost is spread over the cells an attempt covered without inventing or losing any', function () {
    $batch = OverpassBatch::create([]);

    OverpassAttempt::create([
        'overpass_batch_id' => $batch->id,
        'overpass_import_id' => 1,
        'attempt' => 1,
        'latitude_from' => -90, 'latitude_to' => 90,
        'longitude_from' => 10, 'longitude_to' => 12,
        'started_at' => now(),
        'duration_seconds' => 120,
        'response_code' => 200,
        'congested' => false,
    ]);

    $costs = OverpassSeed::measuredCellCosts();

    $total = 0.0;
    foreach ($costs as $column) {
        $total += array_sum($column);
    }

    expect(round($total, 4))->toBe(120.0)
        ->and(array_keys($costs))->toBe([10, 11]);
});

test('a congested attempt is not treated as a measurement of what an area costs', function () {
    $batch = OverpassBatch::create([]);

    OverpassAttempt::create([
        'overpass_batch_id' => $batch->id,
        'overpass_import_id' => 1,
        'attempt' => 1,
        'latitude_from' => -90, 'latitude_to' => 90,
        'longitude_from' => 10, 'longitude_to' => 11,
        'started_at' => now(),
        'duration_seconds' => 8,
        'response_code' => 429,
        'congested' => true,
    ]);

    expect(OverpassSeed::measuredCellCosts())->toBe([]);
});

test('the seed falls back to the uniform grid with no measurements to plan from', function () {
    expect(OverpassSeed::segments())->toHaveCount(360);
});

test('createImports uses the seed and covers the globe', function () {
    $batch = OverpassBatch::create([]);
    $batch->createImports();

    $segments = OverpassImport::where('overpass_batch_id', $batch->id)
        ->get()
        ->map(fn ($i) => [
            'latitude_from' => (int) $i->latitude_from,
            'latitude_to' => (int) $i->latitude_to,
            'longitude_from' => (int) $i->longitude_from,
            'longitude_to' => (int) $i->longitude_to,
        ])->all();

    expect($batch->fresh()->imports_status)->toBe('created')
        ->and(tilingErrors($segments))->toBe(['gaps' => 0, 'overlaps' => 0, 'cells' => 64800]);
});
