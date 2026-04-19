<?php

use App\Models\Spring;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('hasLocalOverride returns false when all fields equal osm_* counterparts', function () {
    $spring = Spring::factory()->create([
        'name' => 'same',         'osm_name' => 'same',
        'type' => 'Spring',       'osm_type' => 'Spring',
        'latitude' => 10.0,       'osm_latitude' => 10.0,
        'longitude' => 20.0,      'osm_longitude' => 20.0,
        'intermittent' => 'yes',  'osm_intermittent' => 'yes',
    ]);

    expect($spring->hasLocalOverride())->toBeFalse();
});

foreach (['name','type','latitude','longitude','intermittent'] as $field) {
    test("hasLocalOverride returns true when {$field} differs from osm_{$field}", function () use ($field) {
        $base = [
            'name' => 'same',         'osm_name' => 'same',
            'type' => 'Spring',       'osm_type' => 'Spring',
            'latitude' => 10.0,       'osm_latitude' => 10.0,
            'longitude' => 20.0,      'osm_longitude' => 20.0,
            'intermittent' => 'yes',  'osm_intermittent' => 'yes',
        ];

        $override = match ($field) {
            'name' => ['name' => 'user override'],
            'type' => ['type' => 'Fountain'],
            'latitude' => ['latitude' => 11.0],
            'longitude' => ['longitude' => 21.0],
            'intermittent' => ['intermittent' => 'no'],
        };

        $spring = Spring::factory()->create(array_merge($base, $override));

        expect($spring->hasLocalOverride())->toBeTrue();
    });
}

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

test('canBePrunedAsMissing returns false when spring has local override', function () {
    $spring = Spring::factory()->create([
        'name' => 'local override',
        'osm_name' => 'osm value',
        'type' => 'Spring', 'osm_type' => 'Spring',
        'latitude' => 10.0, 'osm_latitude' => 10.0,
        'longitude' => 20.0, 'osm_longitude' => 20.0,
    ]);

    expect($spring->canBePrunedAsMissing())->toBeFalse();
});

test('canBePrunedAsMissing returns true when clean: no reports, only from_osm revisions, no override', function () {
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
