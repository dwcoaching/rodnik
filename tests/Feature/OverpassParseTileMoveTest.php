<?php

use App\Library\Overpass;
use App\Models\OverpassBatch;
use App\Models\Spring;
use App\Models\SpringTile;
use App\Models\WateredSpringTile;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('version bump with coord change invalidates old SpringTile and WateredSpringTile', function () {
    $batchOld = OverpassBatch::create([]);
    $batchNew = OverpassBatch::create([]);

    $oldLat = 10.111111;
    $oldLon = 20.222222;
    $newLat = 40.555555;
    $newLon = 50.666666;

    $spring = Spring::factory()->create([
        'osm_node_id' => 1001,
        'osm_version' => 1,
        'osm_name' => 'Alpha spring',
        'name' => 'Alpha spring',
        'osm_type' => 'Spring',
        'type' => 'Spring',
        'osm_latitude' => $oldLat,
        'latitude' => $oldLat,
        'osm_longitude' => $oldLon,
        'longitude' => $oldLon,
        'last_seen_overpass_batch_id' => $batchOld->id,
    ]);

    // Pre-mark old tiles as generated so we can see them invalidated.
    foreach (SpringTile::fromCoordinates($oldLon, $oldLat) as $tile) {
        $tile->generated_at = now();
        $tile->save();
    }
    foreach (WateredSpringTile::fromCoordinates($oldLon, $oldLat) as $tile) {
        $tile->generated_at = now();
        $tile->save();
    }

    // Sanity: old tile coords and new tile coords resolve to different XYZ at zoom 8.
    $oldXYZ = SpringTile::fromCoordinates($oldLon, $oldLat)
        ->map(fn ($t) => $t->z . '/' . $t->x . '/' . $t->y)->all();
    $newXYZ = SpringTile::fromCoordinates($newLon, $newLat)
        ->map(fn ($t) => $t->z . '/' . $t->x . '/' . $t->y)->all();
    expect($oldXYZ)->not->toEqual($newXYZ);

    $json = json_decode(file_get_contents(base_path('tests/stubs/overpass-batch-2-a-moved.json')));
    Overpass::parse($json, $batchNew->id);

    foreach (SpringTile::fromCoordinates($oldLon, $oldLat) as $tile) {
        expect($tile->fresh()->generated_at)->toBeNull();
    }
    foreach (WateredSpringTile::fromCoordinates($oldLon, $oldLat) as $tile) {
        expect($tile->fresh()->generated_at)->toBeNull();
    }
});

test('version bump without coord change does not invalidate extra tiles', function () {
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
    expect((float) $spring->latitude)->toBe(10.111111);
    expect((float) $spring->longitude)->toBe(20.222222);
});
