<?php

use App\Library\PossibleDuplicateSprings;
use App\Livewire\Admin\PossibleDuplicatesTable;
use App\Models\Spring;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function createDuplicateTestSpring(array $attributes): Spring
{
    return Spring::factory()->create(array_merge([
        'name' => 'Test spring',
        'type' => 'Spring',
        'latitude' => 55.000000,
        'longitude' => 37.000000,
        'hidden_at' => null,
        'redirect_to_spring_id' => null,
    ], $attributes));
}

test('possible duplicates are filtered by selected radius after rough coordinate filtering', function () {
    $rodnik = createDuplicateTestSpring([
        'name' => 'Rodnik only',
        'osm_node_id' => null,
        'osm_way_id' => null,
    ]);

    $within50m = createDuplicateTestSpring([
        'name' => 'Within 50m',
        'latitude' => 55.000300,
        'osm_node_id' => 1001,
    ]);

    $within100m = createDuplicateTestSpring([
        'name' => 'Within 100m',
        'latitude' => 55.000800,
        'osm_node_id' => 1002,
    ]);

    $within500m = createDuplicateTestSpring([
        'name' => 'Within 500m',
        'latitude' => 55.004000,
        'osm_node_id' => 1003,
    ]);

    createDuplicateTestSpring([
        'name' => 'Outside 500m',
        'latitude' => 55.006000,
        'osm_node_id' => 1004,
    ]);

    createDuplicateTestSpring([
        'name' => 'Rodnik candidate without OSM neighbor',
        'latitude' => 56.000000,
        'longitude' => 38.000000,
        'osm_node_id' => null,
        'osm_way_id' => null,
    ]);

    expect(PossibleDuplicateSprings::scanCandidates(50)->pluck('osm_id')->all())
        ->toBe([$within50m->id]);

    expect(PossibleDuplicateSprings::scanCandidates(100)->pluck('osm_id')->all())
        ->toBe([$within50m->id]);

    expect(PossibleDuplicateSprings::scanCandidates(500)->pluck('osm_id')->all())
        ->toBe([$within50m->id]);

    expect(PossibleDuplicateSprings::scanCandidates(500)->pluck('rodnik_id')->unique()->values()->all())
        ->toBe([$rodnik->id]);
});

test('possible duplicates include only closest osm source for each rodnik source', function () {
    $firstRodnik = createDuplicateTestSpring([
        'name' => 'First Rodnik only',
        'osm_node_id' => null,
        'osm_way_id' => null,
    ]);

    $secondRodnik = createDuplicateTestSpring([
        'name' => 'Second Rodnik only',
        'latitude' => 56.000000,
        'longitude' => 38.000000,
        'osm_node_id' => null,
        'osm_way_id' => null,
    ]);

    createDuplicateTestSpring([
        'name' => 'Farther first OSM neighbor',
        'latitude' => 55.001000,
        'osm_node_id' => 4001,
    ]);

    $closestFirst = createDuplicateTestSpring([
        'name' => 'Closest first OSM neighbor',
        'latitude' => 55.000100,
        'osm_node_id' => 4002,
    ]);

    $closestSecond = createDuplicateTestSpring([
        'name' => 'Closest second OSM neighbor',
        'latitude' => 56.000100,
        'longitude' => 38.000000,
        'osm_node_id' => 4003,
    ]);

    $duplicates = PossibleDuplicateSprings::scanCandidates(500);

    expect($duplicates->pluck('rodnik_id')->sort()->values()->all())
        ->toBe([$firstRodnik->id, $secondRodnik->id]);
    expect($duplicates->pluck('osm_id')->sort()->values()->all())
        ->toBe([$closestFirst->id, $closestSecond->id]);
});

test('possible duplicates are returned by distance ascending', function () {
    createDuplicateTestSpring([
        'name' => 'First Rodnik only',
        'osm_node_id' => null,
        'osm_way_id' => null,
    ]);

    createDuplicateTestSpring([
        'name' => 'Second Rodnik only',
        'latitude' => 56.000000,
        'longitude' => 38.000000,
        'osm_node_id' => null,
        'osm_way_id' => null,
    ]);

    $closer = createDuplicateTestSpring([
        'name' => 'Closer OSM neighbor',
        'latitude' => 55.000100,
        'osm_node_id' => 3002,
    ]);

    $farther = createDuplicateTestSpring([
        'name' => 'Farther OSM neighbor',
        'latitude' => 56.003000,
        'longitude' => 38.000000,
        'osm_node_id' => 3001,
    ]);

    expect(PossibleDuplicateSprings::scanCandidates(500)->pluck('osm_id')->all())
        ->toBe([$closer->id, $farther->id]);
});

test('possible duplicates exclude hidden and redirected springs', function () {
    $target = createDuplicateTestSpring([
        'name' => 'Redirect target',
        'latitude' => 57.000000,
        'longitude' => 39.000000,
        'osm_node_id' => null,
        'osm_way_id' => null,
    ]);

    createDuplicateTestSpring([
        'name' => 'Hidden Rodnik only',
        'hidden_at' => now(),
        'osm_node_id' => null,
        'osm_way_id' => null,
    ]);

    createDuplicateTestSpring([
        'name' => 'Redirected Rodnik only',
        'latitude' => 55.010000,
        'redirect_to_spring_id' => $target->id,
        'osm_node_id' => null,
        'osm_way_id' => null,
    ]);

    $visibleRodnik = createDuplicateTestSpring([
        'name' => 'Visible Rodnik only',
        'latitude' => 56.000000,
        'longitude' => 38.000000,
        'osm_node_id' => null,
        'osm_way_id' => null,
    ]);

    createDuplicateTestSpring([
        'name' => 'Hidden OSM neighbor',
        'latitude' => 56.000050,
        'longitude' => 38.000000,
        'hidden_at' => now(),
        'osm_node_id' => 5001,
    ]);

    createDuplicateTestSpring([
        'name' => 'Redirected OSM neighbor',
        'latitude' => 56.000060,
        'longitude' => 38.000000,
        'redirect_to_spring_id' => $target->id,
        'osm_node_id' => 5002,
    ]);

    $visibleOsm = createDuplicateTestSpring([
        'name' => 'Visible OSM neighbor',
        'latitude' => 56.000100,
        'longitude' => 38.000000,
        'osm_node_id' => 5003,
    ]);

    $duplicates = PossibleDuplicateSprings::scanCandidates(500);

    expect($duplicates)->toHaveCount(1);
    expect($duplicates->first()->rodnik_id)->toBe($visibleRodnik->id);
    expect($duplicates->first()->osm_id)->toBe($visibleOsm->id);
});

test('possible duplicates respect selected candidate limit', function () {
    foreach (range(1, 101) as $index) {
        $latitude = 55 + ($index / 100);
        $longitude = 37 + ($index / 100);

        createDuplicateTestSpring([
            'name' => "Rodnik only {$index}",
            'latitude' => $latitude,
            'longitude' => $longitude,
            'osm_node_id' => null,
            'osm_way_id' => null,
        ]);

        createDuplicateTestSpring([
            'name' => "OSM neighbor {$index}",
            'latitude' => $latitude + 0.000100,
            'longitude' => $longitude,
            'osm_node_id' => 2000 + $index,
        ]);
    }

    expect(PossibleDuplicateSprings::scanCandidates(500, 100)->count())->toBe(100);
    expect(PossibleDuplicateSprings::normalizeLimit(500))->toBe(500);
    expect(PossibleDuplicateSprings::normalizeLimit(10000))->toBe(10000);
    expect(PossibleDuplicateSprings::normalizeLimit(123))->toBe(100);
});

test('possible duplicates page renders controls and loading shell before table loads', function () {
    createDuplicateTestSpring([
        'name' => 'Rodnik only',
        'osm_node_id' => null,
        'osm_way_id' => null,
    ]);

    createDuplicateTestSpring([
        'name' => 'OSM neighbor',
        'latitude' => 55.000300,
        'osm_way_id' => 2001,
    ]);

    $this->get('/docs/admin/duplicates?radius=50&limit=500')
        ->assertOk()
        ->assertSee('Possible Duplicates')
        ->assertSeeInOrder(['50 m', '100 m', '500 m'])
        ->assertSeeInOrder(['100 sources', '500 sources', '1 000 sources', '10 000 sources'])
        ->assertSee('Looking for possible duplicates...')
        ->assertDontSee('OSM neighbor');
});

test('possible duplicates livewire table loads candidate rows', function () {
    createDuplicateTestSpring([
        'name' => 'Rodnik only',
        'osm_node_id' => null,
        'osm_way_id' => null,
    ]);

    createDuplicateTestSpring([
        'name' => 'OSM neighbor',
        'latitude' => 55.000300,
        'osm_way_id' => 2001,
    ]);

    Livewire::test(PossibleDuplicatesTable::class, ['radius' => 50, 'limit' => 500])
        ->assertSee('Looking for possible duplicates...')
        ->call('load')
        ->assertSee('Rodnik only')
        ->assertSee('OSM neighbor')
        ->assertDontSee('way 2001')
        ->assertSee('Found 1 candidate pair in this batch')
        ->assertSee('seconds.');
});

test('possible duplicates livewire table renders timeout notice', function () {
    Livewire::test(PossibleDuplicatesTable::class, ['radius' => 50, 'limit' => 100])
        ->set('loaded', true)
        ->set('timedOut', true)
        ->set('elapsedSeconds', 20.01)
        ->set('duplicates', [[
            'rodnik_id' => 1,
            'rodnik_name' => 'Rodnik only',
            'rodnik_type' => 'Spring',
            'rodnik_latitude' => 55.000000,
            'rodnik_longitude' => 37.000000,
            'osm_id' => 2,
            'osm_name' => 'OSM neighbor',
            'osm_type' => 'Spring',
            'osm_latitude' => 55.000100,
            'osm_longitude' => 37.000000,
            'osm_node_id' => 2001,
            'osm_way_id' => null,
            'distance_meters' => 11.1,
        ]])
        ->assertSee('Found 1 candidate pair in this batch')
        ->assertSee('result returned after 20.0 seconds')
        ->assertSee('There are more possible duplicates.');
});

test('possible duplicates scan reports timeout metadata', function () {
    createDuplicateTestSpring([
        'name' => 'Rodnik only',
        'osm_node_id' => null,
        'osm_way_id' => null,
    ]);

    $result = PossibleDuplicateSprings::scan(500, 100, 0);

    expect($result['timed_out'])->toBeTrue();
    expect($result['duplicates'])->toHaveCount(0);
    expect($result['elapsed_seconds'])->toBeFloat();
});
