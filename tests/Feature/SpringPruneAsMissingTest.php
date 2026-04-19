<?php

use App\Models\OSMTag;
use App\Models\Spring;
use App\Models\SpringRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeCleanPrunableSpring(): Spring
{
    $spring = Spring::factory()->create([
        'osm_node_id' => 9001,
        'name' => 'same', 'osm_name' => 'same',
        'type' => 'Spring', 'osm_type' => 'Spring',
        'latitude' => 10.0, 'osm_latitude' => 10.0,
        'longitude' => 20.0, 'osm_longitude' => 20.0,
        'intermittent' => null, 'osm_intermittent' => null,
    ]);

    OSMTag::factory()->create(['spring_id' => $spring->id, 'key' => 'natural', 'value' => 'spring']);
    OSMTag::factory()->create(['spring_id' => $spring->id, 'key' => 'name', 'value' => 'same']);

    \DB::table('spring_revisions')->insert([
        'spring_id' => $spring->id,
        'revision_type' => 'from_osm',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $spring->fresh();
}

test('pruneAsMissing deletes the spring row', function () {
    $spring = makeCleanPrunableSpring();
    $id = $spring->id;

    $spring->pruneAsMissing();

    expect(Spring::find($id))->toBeNull();
});

test('pruneAsMissing deletes associated osm_tags', function () {
    $spring = makeCleanPrunableSpring();

    $spring->pruneAsMissing();

    expect(OSMTag::where('spring_id', $spring->id)->count())->toBe(0);
});

test('pruneAsMissing deletes associated spring_revisions including from_osm ones', function () {
    $spring = makeCleanPrunableSpring();

    $spring->pruneAsMissing();

    expect(SpringRevision::where('spring_id', $spring->id)->count())->toBe(0);
});

test('pruneAsMissing throws when canBePrunedAsMissing is false and leaves state intact', function () {
    $spring = Spring::factory()->create([
        'osm_node_id' => 9002,
        'name' => 'local override',
        'osm_name' => 'osm value',
        'type' => 'Spring', 'osm_type' => 'Spring',
        'latitude' => 10.0, 'osm_latitude' => 10.0,
        'longitude' => 20.0, 'osm_longitude' => 20.0,
    ]);

    $tag = OSMTag::factory()->create(['spring_id' => $spring->id, 'key' => 'natural', 'value' => 'spring']);

    expect(fn () => $spring->pruneAsMissing())->toThrow(\Exception::class);

    expect(Spring::find($spring->id))->not->toBeNull();
    expect(OSMTag::find($tag->id))->not->toBeNull();
});
