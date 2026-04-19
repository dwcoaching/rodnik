<?php

use App\Jobs\PruneMissingOSMSprings;
use App\Models\OSMTag;
use App\Models\OverpassBatch;
use App\Models\Spring;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeReadyBatch(): OverpassBatch
{
    $batch = OverpassBatch::create([]);
    $batch->parse_status = 'parsed';
    $batch->coverage = 100;
    $batch->save();
    return $batch;
}

function makePrunableSpring(int $lastSeenBatchId, int $osmNodeId = 9001): Spring
{
    $spring = Spring::factory()->create([
        'osm_node_id' => $osmNodeId,
        'name' => 'same', 'osm_name' => 'same',
        'type' => 'Spring', 'osm_type' => 'Spring',
        'latitude' => 10.0, 'osm_latitude' => 10.0,
        'longitude' => 20.0, 'osm_longitude' => 20.0,
        'intermittent' => null, 'osm_intermittent' => null,
        'last_seen_overpass_batch_id' => $lastSeenBatchId,
    ]);
    OSMTag::factory()->create(['spring_id' => $spring->id, 'key' => 'natural', 'value' => 'spring']);
    return $spring;
}

test('gate: does nothing if parse_status is not parsed', function () {
    $batch = OverpassBatch::create([]);
    $batch->parse_status = 'parsing';
    $batch->coverage = 100;
    $batch->save();

    $spring = makePrunableSpring($batch->id - 1);

    (new PruneMissingOSMSprings($batch))->handle();

    expect(Spring::find($spring->id))->not->toBeNull();
});

test('gate: does nothing if coverage is not 100', function () {
    $batch = OverpassBatch::create([]);
    $batch->parse_status = 'parsed';
    $batch->coverage = 99;
    $batch->save();

    $spring = makePrunableSpring($batch->id - 1);

    (new PruneMissingOSMSprings($batch))->handle();

    expect(Spring::find($spring->id))->not->toBeNull();
});

test('prunes OSM-linked safe spring not seen this batch', function () {
    $batchOld = makeReadyBatch();
    $batchNew = makeReadyBatch();

    $spring = makePrunableSpring($batchOld->id);

    (new PruneMissingOSMSprings($batchNew))->handle();

    expect(Spring::find($spring->id))->toBeNull();
});

test('hides unsafe (has report) missing spring instead of deleting', function () {
    $batchOld = makeReadyBatch();
    $batchNew = makeReadyBatch();

    $spring = makePrunableSpring($batchOld->id);
    \DB::table('reports')->insert([
        'spring_id' => $spring->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    (new PruneMissingOSMSprings($batchNew))->handle();

    $spring->refresh();
    expect($spring->hidden_at)->not->toBeNull();
});

test('leaves non-OSM springs alone regardless of last_seen_overpass_batch_id', function () {
    $batch = makeReadyBatch();
    $spring = Spring::factory()->create([
        'osm_node_id' => null,
        'osm_way_id' => null,
        'last_seen_overpass_batch_id' => null,
    ]);

    (new PruneMissingOSMSprings($batch))->handle();

    expect(Spring::find($spring->id))->not->toBeNull();
    $spring->refresh();
    expect($spring->hidden_at)->toBeNull();
});

test('leaves already-hidden springs alone', function () {
    $batchOld = makeReadyBatch();
    $batchNew = makeReadyBatch();

    $spring = makePrunableSpring($batchOld->id);
    $spring->hidden_at = now();
    $spring->save();

    (new PruneMissingOSMSprings($batchNew))->handle();

    expect(Spring::find($spring->id))->not->toBeNull();
});

test('leaves stamped-this-batch springs alone', function () {
    $batch = makeReadyBatch();
    $spring = makePrunableSpring($batch->id);

    (new PruneMissingOSMSprings($batch))->handle();

    expect(Spring::find($spring->id))->not->toBeNull();
});

test('older prune job does not touch springs stamped by a newer batch', function () {
    $batchOld = makeReadyBatch();
    $batchNew = makeReadyBatch();

    // Spring was last seen alive by the NEWER batch. An older prune job
    // must not treat it as missing just because it wasn't stamped by the
    // older batch's run.
    $spring = makePrunableSpring($batchNew->id);

    (new PruneMissingOSMSprings($batchOld))->handle();

    expect(Spring::find($spring->id))->not->toBeNull();
    $spring->refresh();
    expect($spring->hidden_at)->toBeNull();
});
