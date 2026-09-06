<?php

declare(strict_types=1);

use App\Models\TrackPolygon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->polygon = [
        'type' => 'Polygon',
        'coordinates' => [[[-82.1234567, 34.7654321], [-79.2345678, 34.7654321], [-79.2345678, 40.8765432], [-82.1234567, 34.7654321]]],
    ];
    $this->polygonJson = json_encode($this->polygon, JSON_THROW_ON_ERROR);
    $this->polygonHash = hash('sha256', $this->polygonJson);
    $this->payload = ['hash' => $this->polygonHash, 'polygon' => $this->polygonJson];
});

test('a guest can upload a track polygon with server calculated bounds', function () {
    $forgedUploader = User::factory()->create();

    $response = $this->postJson(route('track-polygons.store'), $this->payload + [
        'user_id' => $forgedUploader->id,
        'latitude_from' => 0,
        'latitude_to' => 0,
        'longitude_from' => 0,
        'longitude_to' => 0,
    ]);

    $response->assertCreated();
    $trackPolygon = TrackPolygon::query()->sole();

    $response->assertExactJson(['id' => $trackPolygon->id, 'hash' => $this->polygonHash]);

    expect($trackPolygon->user_id)->toBeNull()
        ->and($trackPolygon->polygon)->toEqual($this->polygon)
        ->and((float) $trackPolygon->latitude_from)->toBe(34.7654321)
        ->and((float) $trackPolygon->latitude_to)->toBe(40.8765432)
        ->and((float) $trackPolygon->longitude_from)->toBe(-82.1234567)
        ->and((float) $trackPolygon->longitude_to)->toBe(-79.2345678);
});

test('a track polygon records its authenticated uploader from the session', function () {
    $uploader = User::factory()->create();
    $otherUser = User::factory()->create();

    $this->actingAs($uploader)
        ->postJson(route('track-polygons.store'), $this->payload + ['user_id' => $otherUser->id])
        ->assertCreated();

    expect(TrackPolygon::query()->sole()->user_id)->toBe($uploader->id);
});

test('stored bounds contain vertices more precise than seven decimal places', function () {
    $polygon = [
        'type' => 'Polygon',
        'coordinates' => [[
            [-82.123456711, 34.765432189],
            [-79.234567889, 34.765432189],
            [-79.234567889, 40.876543211],
            [-82.123456711, 34.765432189],
        ]],
    ];
    $serialized = json_encode($polygon, JSON_THROW_ON_ERROR);

    $this->postJson(route('track-polygons.store'), [
        'hash' => hash('sha256', $serialized),
        'polygon' => $serialized,
    ])->assertCreated();

    $trackPolygon = TrackPolygon::query()->sole();

    expect((float) $trackPolygon->latitude_from)->toBe(34.7654321)
        ->and((float) $trackPolygon->latitude_to)->toBe(40.8765433)
        ->and((float) $trackPolygon->longitude_from)->toBe(-82.1234568)
        ->and((float) $trackPolygon->longitude_to)->toBe(-79.2345678);
});

test('a polygon can be found by hash without exposing its geometry or uploader', function () {
    $uploader = User::factory()->create();
    $created = $this->actingAs($uploader)
        ->postJson(route('track-polygons.store'), $this->payload)
        ->assertCreated();

    auth()->logout();

    $this->getJson(route('track-polygons.show', ['hash' => $this->polygonHash]))
        ->assertOk()
        ->assertExactJson(['id' => $created->json('id'), 'hash' => $this->polygonHash]);

    $this->assertDatabaseCount('track_polygons', 1);
});

test('lookup returns not found for a missing or malformed polygon hash', function (string $hash) {
    $this->getJson('/track-polygons/'.$hash)->assertNotFound();
})->with([
    'missing polygon' => str_repeat('a', 64),
    'short hash' => 'abc123',
    'uppercase hash' => str_repeat('A', 64),
    'non hexadecimal hash' => str_repeat('z', 64),
]);

test('polygon hash lookups do not consume the polygon upload rate limit', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.21']);

    for ($lookup = 0; $lookup < 31; $lookup++) {
        $this->getJson(route('track-polygons.show', ['hash' => $this->polygonHash]))
            ->assertNotFound();
    }

    $this->postJson(route('track-polygons.store'), $this->payload)->assertCreated();
});

test('repeated uploads reuse the existing polygon and preserve its first uploader', function (bool $guestFirst) {
    $firstUploader = $guestFirst ? null : User::factory()->create();

    if ($firstUploader !== null) {
        $this->actingAs($firstUploader);
    }

    $created = $this->postJson(route('track-polygons.store'), $this->payload)->assertCreated();
    $secondUploader = User::factory()->create();

    $this->actingAs($secondUploader)
        ->postJson(route('track-polygons.store'), $this->payload)
        ->assertOk()
        ->assertExactJson(['id' => $created->json('id'), 'hash' => $this->polygonHash]);

    expect(TrackPolygon::query()->sole()->user_id)->toBe($firstUploader?->id);
})->with([
    'guest first' => true,
    'authenticated first' => false,
]);

test('the polygon remains available when its uploader is deleted', function () {
    $uploader = User::factory()->create();
    $created = $this->actingAs($uploader)
        ->postJson(route('track-polygons.store'), $this->payload)
        ->assertCreated();

    $uploader->delete();

    expect(TrackPolygon::query()->sole()->user_id)->toBeNull();
    $this->getJson(route('track-polygons.show', ['hash' => $this->polygonHash]))
        ->assertOk()
        ->assertExactJson(['id' => $created->json('id'), 'hash' => $this->polygonHash]);
});

test('track polygon creation respects authorization', function () {
    $this->actingAs(User::factory()->create());
    Gate::before(fn (User $user, string $ability): ?bool => $ability === 'create' ? false : null);

    $this->postJson(route('track-polygons.store'), $this->payload)->assertForbidden();

    $this->assertDatabaseEmpty('track_polygons');
});

test('polygon upload requires a serialized geometry and valid hash', function (array $payload, array $fields) {
    $this->postJson(route('track-polygons.store'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($fields);

    $this->assertDatabaseEmpty('track_polygons');
})->with([
    'missing fields' => [[], ['hash', 'polygon']],
    'missing polygon' => [['hash' => str_repeat('a', 64)], ['polygon']],
    'missing hash' => [['polygon' => '{}'], ['hash']],
    'short hash' => [['hash' => 'abc', 'polygon' => '{}'], ['hash']],
    'uppercase hash' => [['hash' => str_repeat('A', 64), 'polygon' => '{}'], ['hash']],
    'non hexadecimal hash' => [['hash' => str_repeat('z', 64), 'polygon' => '{}'], ['hash']],
    'numeric hash' => [['hash' => 123, 'polygon' => '{}'], ['hash']],
    'array instead of serialized geometry' => [['hash' => str_repeat('a', 64), 'polygon' => ['type' => 'Polygon']], ['polygon']],
]);

test('the server rejects a hash that does not match the uploaded polygon', function () {
    $this->postJson(route('track-polygons.store'), [
        'hash' => str_repeat('a', 64),
        'polygon' => $this->polygonJson,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('hash');

    $this->assertDatabaseEmpty('track_polygons');
});

test('an existing hash does not bypass verification of a repeated upload', function () {
    $this->postJson(route('track-polygons.store'), $this->payload)->assertCreated();

    $changedPolygon = $this->polygon;
    $changedPolygon['coordinates'][0][1] = [-80, 35];

    $this->postJson(route('track-polygons.store'), [
        'hash' => $this->polygonHash,
        'polygon' => json_encode($changedPolygon, JSON_THROW_ON_ERROR),
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('hash');

    expect(TrackPolygon::query()->sole()->polygon)->toEqual($this->polygon);
});

test('hash verification uses the supplied serialization without reencoding the geometry', function () {
    $serialized = json_encode($this->polygon, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    $hash = hash('sha256', $serialized);

    expect($hash)->not->toBe($this->polygonHash);

    $this->postJson(route('track-polygons.store'), ['hash' => $hash, 'polygon' => $serialized])
        ->assertCreated()
        ->assertJsonPath('hash', $hash);

    expect(TrackPolygon::query()->sole()->polygon)->toEqual($this->polygon);
});

test('hash verification preserves exterior whitespace in the serialized polygon', function () {
    $serialized = "\n\t ".$this->polygonJson." \r\n";
    $hash = hash('sha256', $serialized);

    expect($hash)->not->toBe($this->polygonHash);

    $this->postJson(route('track-polygons.store'), ['hash' => $hash, 'polygon' => $serialized])
        ->assertCreated()
        ->assertJsonPath('hash', $hash);

    expect(TrackPolygon::query()->sole()->polygon)->toEqual($this->polygon);
});

test('a polygon ring can close with equivalent integer and decimal coordinates', function () {
    $serialized = '{"type":"Polygon","coordinates":[[[0,0],[1,0],[1,1],[0.0,0.0]]]}';

    $this->postJson(route('track-polygons.store'), [
        'hash' => hash('sha256', $serialized),
        'polygon' => $serialized,
    ])->assertCreated();

    expect(TrackPolygon::query()->sole()->polygon)
        ->toEqual(json_decode($serialized, true, 32, JSON_THROW_ON_ERROR));
});

test('malformed polygon geometries are rejected before storage', function (string $serialized) {
    $this->postJson(route('track-polygons.store'), [
        'hash' => hash('sha256', $serialized),
        'polygon' => $serialized,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('polygon');

    $this->assertDatabaseEmpty('track_polygons');
})->with([
    'malformed JSON' => '{',
    'null JSON' => 'null',
    'array root' => '[]',
    'unsupported geometry' => '{"type":"LineString","coordinates":[[0,0],[1,1]]}',
    'feature wrapper' => '{"type":"Feature","geometry":{"type":"Polygon","coordinates":[[[0,0],[1,0],[1,1],[0,0]]]}}',
    'extra root properties' => '{"type":"Polygon","coordinates":[[[0,0],[1,0],[1,1],[0,0]]],"properties":{}}',
    'missing coordinates' => '{"type":"Polygon"}',
    'empty polygon' => '{"type":"Polygon","coordinates":[]}',
    'empty ring' => '{"type":"Polygon","coordinates":[[]]}',
    'unclosed ring' => '{"type":"Polygon","coordinates":[[[0,0],[1,0],[1,1],[0,1]]]}',
    'too few vertices' => '{"type":"Polygon","coordinates":[[[0,0],[1,1],[0,0]]]}',
    'too few distinct vertices' => '{"type":"Polygon","coordinates":[[[0,0],[1,1],[1,1],[0,0]]]}',
    'longitude out of range' => '{"type":"Polygon","coordinates":[[[181,0],[1,0],[1,1],[181,0]]]}',
    'latitude out of range' => '{"type":"Polygon","coordinates":[[[0,-91],[1,0],[1,1],[0,-91]]]}',
    'numeric string coordinate' => '{"type":"Polygon","coordinates":[[["0",0],[1,0],[1,1],["0",0]]]}',
    'boolean coordinate' => '{"type":"Polygon","coordinates":[[[false,0],[1,0],[1,1],[false,0]]]}',
    'non finite coordinate' => '{"type":"Polygon","coordinates":[[[1e400,0],[1,0],[1,1],[1e400,0]]]}',
    'altitude coordinate' => '{"type":"Polygon","coordinates":[[[0,0,5],[1,0,5],[1,1,5],[0,0,5]]]}',
    'object instead of coordinate pair' => '{"type":"Polygon","coordinates":[[{"0":0,"1":0},[1,0],[1,1],[0,0]]]}',
    'unclosed inner ring' => '{"type":"Polygon","coordinates":[[[0,0],[4,0],[4,4],[0,0]],[[1,1],[2,1],[2,2],[1,2]]]}',
    'empty multipolygon' => '{"type":"MultiPolygon","coordinates":[]}',
    'empty multipolygon member' => '{"type":"MultiPolygon","coordinates":[[]]}',
]);

test('multipolygons with holes are stored with bounds covering all component polygons', function () {
    $polygon = [
        'type' => 'MultiPolygon',
        'coordinates' => [
            [
                [[-82, 34], [-78, 34], [-78, 40], [-82, 40], [-82, 34]],
                [[-81, 35], [-81, 36], [-80, 36], [-80, 35], [-81, 35]],
            ],
            [
                [[-88, 30], [-85, 30], [-85, 32], [-88, 30]],
            ],
        ],
    ];
    $serialized = json_encode($polygon, JSON_THROW_ON_ERROR);

    $this->postJson(route('track-polygons.store'), [
        'hash' => hash('sha256', $serialized),
        'polygon' => $serialized,
    ])->assertCreated();

    $trackPolygon = TrackPolygon::query()->sole();

    expect($trackPolygon->polygon)->toEqual($polygon)
        ->and((float) $trackPolygon->latitude_from)->toBe(30.0)
        ->and((float) $trackPolygon->latitude_to)->toBe(40.0)
        ->and((float) $trackPolygon->longitude_from)->toBe(-88.0)
        ->and((float) $trackPolygon->longitude_to)->toBe(-78.0);
});

test('a large polygon comparable to the Appalachian Trail buffer is stored without truncation', function () {
    $ring = [];

    for ($vertex = 0; $vertex < 5578; $vertex++) {
        $angle = 2 * M_PI * $vertex / 5578;
        $ring[] = [-80 + cos($angle), 40 + sin($angle)];
    }

    $ring[] = $ring[0];
    $polygon = ['type' => 'Polygon', 'coordinates' => [$ring]];
    $serialized = json_encode($polygon, JSON_THROW_ON_ERROR);
    $hash = hash('sha256', $serialized);

    expect(mb_strlen($serialized))->toBeGreaterThan(65535);

    $this->postJson(route('track-polygons.store'), ['hash' => $hash, 'polygon' => $serialized])
        ->assertCreated();

    expect(TrackPolygon::query()->sole()->polygon)->toEqual($polygon);
});

test('polygons larger than one MiB are rejected', function () {
    $serialized = str_replace('"type":', '"type":'.str_repeat(' ', 1024 * 1024), $this->polygonJson);

    $this->postJson(route('track-polygons.store'), [
        'hash' => hash('sha256', $serialized),
        'polygon' => $serialized,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('polygon');

    $this->assertDatabaseEmpty('track_polygons');
});

test('polygons with more than fifty thousand total vertices are rejected', function () {
    $ring = array_fill(0, 25000, [0, 0]);
    $ring[1] = [1, 0];
    $ring[2] = [1, 1];
    $ring[] = $ring[0];
    $serialized = json_encode(['type' => 'MultiPolygon', 'coordinates' => [[$ring], [$ring]]], JSON_THROW_ON_ERROR);

    expect(mb_strlen($serialized))->toBeLessThan(1024 * 1024);

    $this->postJson(route('track-polygons.store'), [
        'hash' => hash('sha256', $serialized),
        'polygon' => $serialized,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('polygon');

    $this->assertDatabaseEmpty('track_polygons');
});
