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

/**
 * A byte map shaped like southern India: two columns quick to answer but huge, with nearly all
 * of their bytes between 8 and 20 degrees north.
 *
 * @return array<int, array<int, float>>
 */
function indiaByteMap(): array
{
    $bytes = [];

    for ($lon = -180; $lon < 180; $lon++) {
        for ($lat = -90; $lat < 90; $lat++) {
            $bytes[$lon][$lat] = 1_000.0;
        }
    }

    foreach ([74, 75] as $lon) {
        for ($lat = 8; $lat < 20; $lat++) {
            $bytes[$lon][$lat] = 2_900_000.0; // ~35 MB per column
        }
    }

    return $bytes;
}

test('a column that is quick to answer but huge is split by its bytes, not merged by its seconds', function () {
    // Every column is cheap in seconds, so on time alone the dense ones would merge with others.
    $segments = OverpassSeed::segmentsFor(costMap(cheap: 0.05), indiaByteMap());

    $touching = array_values(array_filter($segments, fn ($s) => $s['longitude_from'] < 76 && $s['longitude_to'] > 74));

    foreach ($touching as $segment) {
        expect($segment['longitude_to'] - $segment['longitude_from'])->toBe(1);
    }

    $bytes = indiaByteMap();
    foreach ($segments as $segment) {
        $size = 0.0;
        for ($lon = $segment['longitude_from']; $lon < $segment['longitude_to']; $lon++) {
            for ($lat = $segment['latitude_from']; $lat < $segment['latitude_to']; $lat++) {
                $size += $bytes[$lon][$lat];
            }
        }

        expect($size)->toBeLessThanOrEqual(OverpassSeed::TARGET_BYTES + 3_000_000.0);
    }

    expect(tilingErrors($segments))->toBe(['gaps' => 0, 'overlaps' => 0, 'cells' => 64800]);
});

test('runs of cheap columns stop growing before their responses add up past the byte target', function () {
    $bytes = [];
    for ($lon = -180; $lon < 180; $lon++) {
        for ($lat = -90; $lat < 90; $lat++) {
            $bytes[$lon][$lat] = 50_000.0; // 9 MB per column
        }
    }

    $segments = OverpassSeed::segmentsFor(costMap(cheap: 0.01), $bytes);
    $widths = array_map(fn ($s) => $s['longitude_to'] - $s['longitude_from'], $segments);

    expect(max($widths))->toBe(2)
        ->and(tilingErrors($segments))->toBe(['gaps' => 0, 'overlaps' => 0, 'cells' => 64800]);
});

test('response bytes are spread over the cells an attempt covered', function () {
    $batch = OverpassBatch::create([]);

    OverpassAttempt::create([
        'overpass_batch_id' => $batch->id,
        'overpass_import_id' => 1,
        'attempt' => 1,
        'latitude_from' => -90, 'latitude_to' => 90,
        'longitude_from' => 74, 'longitude_to' => 76,
        'started_at' => now(),
        'duration_seconds' => 32,
        'response_code' => 200,
        'response_bytes' => 69_583_852,
        'congested' => false,
    ]);

    $bytes = OverpassSeed::measuredCellBytes();

    $total = 0.0;
    foreach ($bytes as $column) {
        $total += array_sum($column);
    }

    expect(round($total))->toBe(69_583_852.0)
        ->and(array_keys($bytes))->toBe([74, 75]);
});
