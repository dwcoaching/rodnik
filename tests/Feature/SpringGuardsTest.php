<?php

use App\Models\Spring;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('canBePrunedAsMissing returns false when spring has any report (even hidden)', function () {
    $spring = Spring::factory()->create([
        'name' => 'a', 'osm_name' => 'a',
        'type' => 'Spring', 'osm_type' => 'Spring',
        'latitude' => 10.0, 'osm_latitude' => 10.0,
        'longitude' => 20.0, 'osm_longitude' => 20.0,
    ]);

    \DB::table('reports')->insert([
        'spring_id' => $spring->id,
        'hidden_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect($spring->canBePrunedAsMissing())->toBeFalse();
});

test('canBePrunedAsMissing returns false when non-OSM SpringRevision exists', function () {
    $spring = Spring::factory()->create([
        'name' => 'a', 'osm_name' => 'a',
        'type' => 'Spring', 'osm_type' => 'Spring',
        'latitude' => 10.0, 'osm_latitude' => 10.0,
        'longitude' => 20.0, 'osm_longitude' => 20.0,
    ]);

    \DB::table('spring_revisions')->insert([
        'spring_id' => $spring->id,
        'revision_type' => 'user_edit',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect($spring->canBePrunedAsMissing())->toBeFalse();
});

test('canBePrunedAsMissing returns true when clean: no reports, only from_osm revisions', function () {
    $spring = Spring::factory()->create([
        'name' => 'same', 'osm_name' => 'same',
        'type' => 'Spring', 'osm_type' => 'Spring',
        'latitude' => 10.0, 'osm_latitude' => 10.0,
        'longitude' => 20.0, 'osm_longitude' => 20.0,
        'intermittent' => null, 'osm_intermittent' => null,
    ]);

    \DB::table('spring_revisions')->insert([
        'spring_id' => $spring->id,
        'revision_type' => 'from_osm',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect($spring->canBePrunedAsMissing())->toBeTrue();
});
