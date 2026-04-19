<?php

use App\Jobs\CleanupOSMSprings;
use App\Jobs\RemoveOlderOverpassArtifacts;
use App\Library\Overpass;
use App\Models\OverpassBatch;
use App\Models\OverpassImport;
use App\Models\Spring;
use App\Models\SpringRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('spring revision is created', function () {
    $json = file_get_contents(base_path('tests/stubs/overpass.json'));

    $result = Overpass::parse(json_decode($json));

    // Find the spring with the OSM node ID from our test data
    $spring = Spring::where('osm_node_id', 7600556407)->first();

    // Get the revision for that spring
    $springRevision = SpringRevision::where('spring_id', $spring->id)
        ->orderBy('id', 'desc')->first();

    expect($springRevision)->not->toBeNull();
    expect(55.655136)->toEqual($springRevision->old_latitude);
    expect(36.709845)->toEqual($springRevision->old_longitude);
    expect(55.655135)->toEqual($springRevision->new_latitude);
    expect(36.709844)->toEqual($springRevision->new_longitude);
    expect('Родник святого Дионисия')->toEqual($springRevision->new_name);
});

test('batch parse adds new springs and updates existing ones', function () {
    Storage::fake('local');
    Queue::fake();

    // Pre-existing spring matching one of the OSM nodes in the stub
    $existing = Spring::factory()->create([
        'osm_node_id' => 7600556407,
        'name' => 'Old name',
        'osm_name' => 'Old name',
        'latitude' => 0.0,
        'longitude' => 0.0,
        'osm_latitude' => 0.0,
        'osm_longitude' => 0.0,
    ]);

    $batch = new OverpassBatch();
    $batch->imports_status = 'created';
    $batch->checks_status = 'created';
    $batch->fetch_status = 'fetched';
    $batch->parse_status = 'not started';
    $batch->save();

    $import = new OverpassImport();
    $import->overpass_batch_id = $batch->id;
    $import->latitude_from = -90;
    $import->latitude_to = 90;
    $import->longitude_from = -180;
    $import->longitude_to = 180;
    $import->started_at = now();
    $import->fetched_at = now();
    $import->response_code = 200;
    $import->response_phrase = 'OK';
    $import->has_remarks = 0;
    $import->ground_up = false;
    $import->save();

    // Inject stub Overpass response (writes to faked storage at the import's responsePath)
    $import->response = file_get_contents(base_path('tests/stubs/overpass.json'));

    $batch->parseImports();

    // Existing spring kept (same id) and updated from OSM
    $existing->refresh();
    expect($existing->osm_name)->toEqual('Родник святого Дионисия');
    expect((float) $existing->osm_latitude)->toEqual(55.655135);
    expect((float) $existing->osm_longitude)->toEqual(36.709844);

    // New spring created from the second node in the stub
    $newSpring = Spring::where('osm_node_id', 5070099876)->first();
    expect($newSpring)->not->toBeNull();
    expect($newSpring->osm_name)->toEqual('arroyo');
    expect((float) $newSpring->osm_latitude)->toEqual(-35.755032);
    expect((float) $newSpring->osm_longitude)->toEqual(-58.502391);

    // Batch reached 100% parsed and dispatched terminal cleanup jobs
    $batch->refresh();
    expect($batch->parse_status)->toEqual('parsed');
    Queue::assertPushed(CleanupOSMSprings::class);
    Queue::assertPushed(RemoveOlderOverpassArtifacts::class);
});
