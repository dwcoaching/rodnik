<?php

declare(strict_types=1);

use App\Library\Overpass;
use App\Models\OSMTag;
use App\Models\OverpassBatch;
use App\Models\Spring;
use App\Models\SpringRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('fast path: unchanged version produces no revision and no tag refresh', function () {
    $batchOld = OverpassBatch::create([]);
    $batchNew = OverpassBatch::create([]);

    $spring = Spring::factory()->create([
        'osm_node_id' => 1001,
        'osm_version' => 1,
        'osm_name' => 'Alpha spring',
        'name' => 'Alpha spring',
        'osm_type' => 'Spring',
        'type' => 'Spring',
        'osm_latitude' => 10.111111,
        'latitude' => 10.111111,
        'osm_longitude' => 20.222222,
        'longitude' => 20.222222,
        'last_seen_overpass_batch_id' => $batchOld->id,
    ]);

    OSMTag::factory()->create(['spring_id' => $spring->id, 'key' => 'natural', 'value' => 'spring']);
    OSMTag::factory()->create(['spring_id' => $spring->id, 'key' => 'name', 'value' => 'Alpha spring']);

    $revisionsBefore = SpringRevision::where('spring_id', $spring->id)->count();
    $tagIdsBefore = OSMTag::where('spring_id', $spring->id)->pluck('id')->sort()->values()->all();

    $json = json_decode(file_get_contents(base_path('tests/stubs/overpass-batch-2-a-only.json')));
    $stats = Overpass::parse($json, $batchNew->id);

    $spring->refresh();

    expect($stats->unchanged)->toBe(1);
    expect(SpringRevision::where('spring_id', $spring->id)->count())->toBe($revisionsBefore);
    expect(OSMTag::where('spring_id', $spring->id)->pluck('id')->sort()->values()->all())
        ->toEqual($tagIdsBefore);
    expect($spring->last_seen_overpass_batch_id)->toBe($batchNew->id);
});

test('OSM updates treat database decimal strings and incoming coordinate floats as equal', function () {
    $spring = new Spring();
    $spring->setRawAttributes([
        'latitude' => '10.111111',
        'osm_latitude' => '10.111111',
        'longitude' => '20.222222',
        'osm_longitude' => '20.222222',
    ]);
    $spring->syncOriginal();

    $revision = $spring->updateFromOSM('latitude', 10.111111, new SpringRevision());
    $revision = $spring->updateFromOSM('longitude', 20.222222, $revision);

    expect($revision->isDirty())->toBeFalse()
        ->and($spring->isDirty())->toBeFalse();
});

test('OSM updates still record meaningful coordinate changes', function () {
    $spring = new Spring();
    $spring->setRawAttributes([
        'latitude' => '10.111111',
        'osm_latitude' => '10.111111',
    ]);

    $revision = $spring->updateFromOSM('latitude', 10.111112, new SpringRevision());

    expect($revision->old_latitude)->toBe('10.111111')
        ->and($revision->new_latitude)->toBe(10.111112)
        ->and($revision->old_osm_latitude)->toBe('10.111111')
        ->and($revision->new_osm_latitude)->toBe(10.111112)
        ->and($spring->latitude)->toBe(10.111112)
        ->and($spring->osm_latitude)->toBe(10.111112);
});

test('slow path: version bump updates osm_name and writes revision', function () {
    $batchOld = OverpassBatch::create([]);
    $batchNew = OverpassBatch::create([]);

    $spring = Spring::factory()->create([
        'osm_node_id' => 1001,
        'osm_version' => 1,
        'osm_name' => 'Alpha spring',
        'name' => 'Alpha spring',
        'osm_type' => 'Spring',
        'type' => 'Spring',
        'osm_latitude' => 10.111111,
        'latitude' => 10.111111,
        'osm_longitude' => 20.222222,
        'longitude' => 20.222222,
        'last_seen_overpass_batch_id' => $batchOld->id,
    ]);

    $json = json_decode(file_get_contents(base_path('tests/stubs/overpass-batch-2-a-updated.json')));
    Overpass::parse($json, $batchNew->id);

    $spring->refresh();

    expect($spring->osm_name)->toBe('Alpha spring renamed');
    expect($spring->osm_version)->toBe(2);
    expect($spring->last_seen_overpass_batch_id)->toBe($batchNew->id);

    $revision = SpringRevision::where('spring_id', $spring->id)->latest('id')->first();
    expect($revision)->not->toBeNull();
    expect($revision->revision_type)->toBe('from_osm');
    expect($revision->new_osm_name)->toBe('Alpha spring renamed');
});

test('slow path: new spring gets osm_version and last_seen_overpass_batch_id', function () {
    $batch = OverpassBatch::create([]);

    $json = json_decode(file_get_contents(base_path('tests/stubs/overpass-batch-1.json')));
    $stats = Overpass::parse($json, $batch->id);

    expect($stats->new)->toBe(2);

    foreach ([1001, 1002] as $osmNodeId) {
        $spring = Spring::where('osm_node_id', $osmNodeId)->first();
        expect($spring)->not->toBeNull();
        expect($spring->osm_version)->toBe(1);
        expect($spring->last_seen_overpass_batch_id)->toBe($batch->id);
    }
});

test('stamp always applied: fast-path spring and slow-path spring both get last_seen stamped', function () {
    $batchOld = OverpassBatch::create([]);
    $batchNew = OverpassBatch::create([]);

    $springFast = Spring::factory()->create([
        'osm_node_id' => 1001,
        'osm_version' => 1,
        'osm_name' => 'Alpha spring',
        'name' => 'Alpha spring',
        'osm_type' => 'Spring',
        'type' => 'Spring',
        'osm_latitude' => 10.111111,
        'latitude' => 10.111111,
        'osm_longitude' => 20.222222,
        'longitude' => 20.222222,
        'last_seen_overpass_batch_id' => $batchOld->id,
    ]);
    OSMTag::factory()->create(['spring_id' => $springFast->id, 'key' => 'natural', 'value' => 'spring']);

    $json = json_decode(file_get_contents(base_path('tests/stubs/overpass-batch-1.json')));
    Overpass::parse($json, $batchNew->id);

    $springFast->refresh();
    expect($springFast->last_seen_overpass_batch_id)->toBe($batchNew->id);

    $springSlowNew = Spring::where('osm_node_id', 1002)->first();
    expect($springSlowNew)->not->toBeNull();
    expect($springSlowNew->last_seen_overpass_batch_id)->toBe($batchNew->id);
});

test('parse accepts batchId as second argument and returns unchanged count', function () {
    $batch = OverpassBatch::create([]);
    $json = json_decode(file_get_contents(base_path('tests/stubs/overpass-batch-1.json')));

    $stats = Overpass::parse($json, $batch->id);

    expect($stats)->toHaveProperty('new');
    expect($stats)->toHaveProperty('existing');
    expect($stats)->toHaveProperty('unchanged');
});

function overpassWay(int $id, int $version, float $lat, float $lon): object
{
    return (object) [
        'type' => 'way',
        'id' => $id,
        'version' => $version,
        'center' => (object) ['lat' => $lat, 'lon' => $lon],
        'tags' => (object) ['natural' => 'spring', 'name' => 'Way spring'],
    ];
}

function springForWay(int $id, int $version, float $lat, float $lon, int $batchId): Spring
{
    $spring = Spring::factory()->create([
        'osm_way_id' => $id,
        'osm_version' => $version,
        'osm_name' => 'Way spring',
        'name' => 'Way spring',
        'osm_type' => 'Spring',
        'type' => 'Spring',
        'osm_latitude' => $lat,
        'latitude' => $lat,
        'osm_longitude' => $lon,
        'longitude' => $lon,
        'last_seen_overpass_batch_id' => $batchId,
    ]);

    OSMTag::factory()->create(['spring_id' => $spring->id, 'key' => 'natural', 'value' => 'spring']);
    OSMTag::factory()->create(['spring_id' => $spring->id, 'key' => 'name', 'value' => 'Way spring']);

    return $spring;
}

test('fast path: a way at its stored version and centre is left alone', function () {
    $batchOld = OverpassBatch::create([]);
    $batchNew = OverpassBatch::create([]);
    $spring = springForWay(5001, 3, 10.111111, 20.222222, $batchOld->id);

    $stats = Overpass::parse((object) ['elements' => [overpassWay(5001, 3, 10.1111111, 20.2222219)]], $batchNew->id);

    $spring->refresh();

    expect($stats->unchanged)->toBe(1)
        ->and($stats->existing)->toBe(0)
        ->and(SpringRevision::where('spring_id', $spring->id)->count())->toBe(0)
        ->and($spring->last_seen_overpass_batch_id)->toBe($batchNew->id);
});

test('slow path: a way whose nodes moved is updated even though its version did not change', function () {
    $batchOld = OverpassBatch::create([]);
    $batchNew = OverpassBatch::create([]);
    $spring = springForWay(5001, 3, 10.111111, 20.222222, $batchOld->id);

    $stats = Overpass::parse((object) ['elements' => [overpassWay(5001, 3, 10.111311, 20.222222)]], $batchNew->id);

    $spring->refresh();
    $revision = SpringRevision::where('spring_id', $spring->id)->latest('id')->first();

    expect($stats->existing)->toBe(1)
        ->and($stats->unchanged)->toBe(0)
        ->and((float) $spring->latitude)->toBe(10.111311)
        ->and((float) $spring->osm_latitude)->toBe(10.111311)
        ->and($spring->osm_version)->toBe(3)
        ->and($spring->last_seen_overpass_batch_id)->toBe($batchNew->id)
        ->and($revision?->revision_type)->toBe('from_osm')
        ->and((float) $revision?->new_osm_latitude)->toBe(10.111311);
});

test('unchanged springs are looked up and stamped per chunk, not one by one', function () {
    $batchOld = OverpassBatch::create([]);
    $batchNew = OverpassBatch::create([]);
    $elements = [];

    foreach (range(1, 30) as $i) {
        Spring::factory()->create([
            'osm_node_id' => 9000 + $i,
            'osm_version' => 1,
            'osm_latitude' => 10 + $i / 100,
            'latitude' => 10 + $i / 100,
            'osm_longitude' => 20,
            'longitude' => 20,
            'last_seen_overpass_batch_id' => $batchOld->id,
        ]);

        $elements[] = (object) [
            'type' => 'node',
            'id' => 9000 + $i,
            'version' => 1,
            'lat' => 10 + $i / 100,
            'lon' => 20,
            'tags' => (object) ['natural' => 'spring'],
        ];
    }

    $queries = 0;
    DB::listen(function () use (&$queries) {
        $queries++;
    });

    $stats = Overpass::parse((object) ['elements' => $elements], $batchNew->id);

    expect($stats->unchanged)->toBe(30)
        ->and($queries)->toBe(2)
        ->and(Spring::where('last_seen_overpass_batch_id', $batchNew->id)->count())->toBe(30);
});
