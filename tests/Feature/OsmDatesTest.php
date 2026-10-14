<?php

declare(strict_types=1);

use App\Jobs\PruneMissingOSMSprings;
use App\Library\Overpass;
use App\Livewire\Duo\Springs\Show;
use App\Models\OSMTag;
use App\Models\OverpassBatch;
use App\Models\Report;
use App\Models\Spring;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function osmResponse(array $elements, string $base = '2026-10-13T20:43:57Z'): object
{
    return (object) [
        'osm3s' => (object) ['timestamp_osm_base' => $base],
        'elements' => $elements,
    ];
}

function osmNode(int $id, int $version, string $timestamp): object
{
    return (object) [
        'type' => 'node',
        'id' => $id,
        'version' => $version,
        'timestamp' => $timestamp,
        'lat' => 10.5,
        'lon' => 20.5,
        'tags' => (object) ['natural' => 'spring'],
    ];
}

function parsedBatch(): OverpassBatch
{
    $batch = OverpassBatch::create([]);
    $batch->parse_status = 'parsed';
    $batch->coverage = 100;
    $batch->save();

    return $batch;
}

test('a new spring keeps its OSM edit time and when the import saw it', function () {
    $batch = OverpassBatch::create([]);

    Overpass::parse(osmResponse([osmNode(7001, 4, '2024-09-22T20:08:07Z')]), $batch->id);

    $spring = Spring::where('osm_node_id', 7001)->first();

    expect($spring->osm_version)->toBe(4)
        ->and($spring->osm_timestamp->toDateTimeString())->toBe('2024-09-22 20:08:07')
        ->and($spring->osm_last_seen_at->toDateTimeString())->toBe('2026-10-13 20:43:57')
        ->and($spring->osm_missing_since)->toBeNull();
});

test('an unchanged spring stored before edit times were kept gets one without a full update', function () {
    $batch = OverpassBatch::create([]);
    $spring = Spring::factory()->create([
        'osm_node_id' => 7001,
        'osm_version' => 4,
        'osm_latitude' => 10.5,
        'latitude' => 10.5,
        'osm_longitude' => 20.5,
        'longitude' => 20.5,
        'osm_missing_since' => '2026-09-01 00:00:00',
    ]);

    $stats = Overpass::parse(osmResponse([osmNode(7001, 4, '2024-09-22T20:08:07Z')]), $batch->id);

    $spring->refresh();

    expect($stats->unchanged)->toBe(1)
        ->and($spring->osm_timestamp->toDateTimeString())->toBe('2024-09-22 20:08:07')
        ->and($spring->osm_last_seen_at->toDateTimeString())->toBe('2026-10-13 20:43:57')
        ->and($spring->last_seen_overpass_batch_id)->toBe($batch->id)
        ->and($spring->osm_missing_since)->toBeNull();
});

test('a complete batch dates the springs it kept but no longer saw, and leaves earlier dates alone', function () {
    $previous = OverpassBatch::create([]);
    $batch = parsedBatch();
    $batch->created_at = '2026-10-13 00:17:43';
    $batch->save();

    $gone = Spring::factory()->create(['osm_node_id' => 1, 'last_seen_overpass_batch_id' => $previous->id]);
    $goneLongAgo = Spring::factory()->create(['osm_node_id' => 2, 'osm_missing_since' => '2026-05-01 00:00:00']);
    $present = Spring::factory()->create(['osm_node_id' => 3, 'last_seen_overpass_batch_id' => $batch->id]);

    // Reports keep the missing springs from being pruned outright.
    Report::factory()->create(['spring_id' => $gone->id]);
    Report::factory()->create(['spring_id' => $goneLongAgo->id]);

    (new PruneMissingOSMSprings($batch))->handle();

    expect($gone->fresh()->osm_missing_since->toDateTimeString())->toBe('2026-10-13 00:17:43')
        ->and($goneLongAgo->fresh()->osm_missing_since->toDateTimeString())->toBe('2026-05-01 00:00:00')
        ->and($present->fresh()->osm_missing_since)->toBeNull();
});

function springGoneFromOsm(): Spring
{
    $spring = Spring::withoutEvents(fn () => Spring::factory()->create([
        'osm_node_id' => 7001,
        'osm_version' => 4,
        'osm_timestamp' => '2024-09-22 20:08:07',
        'osm_last_seen_at' => '2026-10-08 20:21:38',
        'osm_missing_since' => '2026-10-13 00:17:43',
    ]));

    OSMTag::factory()->create(['spring_id' => $spring->id, 'key' => 'natural', 'value' => 'spring']);

    return $spring;
}

test('admins see when a source was last edited in OSM and when it disappeared, like everyone', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $spring = springGoneFromOsm();

    Livewire::test(Show::class, ['springId' => $spring->id])
        ->assertSee('gone from OSM')
        ->assertSee('Last edited in OSM:')
        ->assertSee('22.09.2024 · version 4')
        ->assertSee('Disappeared between 08.10.2026 and 13.10.2026.');
});

test('without a last sighting admins only see the date it was gone by', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $spring = springGoneFromOsm();
    $spring->forceFill(['osm_last_seen_at' => null])->saveQuietly();

    Livewire::test(Show::class, ['springId' => $spring->id])
        ->assertSee('Disappeared by 13.10.2026.')
        ->assertDontSee('Disappeared between');
});

test('everyone sees when a source was last edited in OSM and when it disappeared', function (bool $signedIn) {
    if ($signedIn) {
        $this->actingAs(User::factory()->create(['is_admin' => false, 'is_superadmin' => false]));
    }

    $spring = springGoneFromOsm();

    Livewire::test(Show::class, ['springId' => $spring->id])
        ->assertSee('gone from OSM')
        ->assertSee('22.09.2024 · version 4')
        ->assertSee('Disappeared between 08.10.2026 and 13.10.2026.');
})->with(['guest' => false, 'signed in' => true]);

test('a source still in OSM shows its last edit but no sign of having disappeared', function () {
    $spring = springGoneFromOsm();
    $spring->forceFill(['osm_missing_since' => null])->saveQuietly();

    Livewire::test(Show::class, ['springId' => $spring->id])
        ->assertSee('22.09.2024 · version 4')
        ->assertDontSee('gone from OSM')
        ->assertDontSee('Disappeared');
});
