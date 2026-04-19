<?php

use App\Library\Overpass;
use App\Models\OSMTag;
use App\Models\OverpassBatch;
use App\Models\Spring;
use App\Models\SpringRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
