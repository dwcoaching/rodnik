<?php

declare(strict_types=1);

use App\Models\Track;
use App\Models\User;
use App\Rules\GeoJsonTrackRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

beforeEach(function () {
    gc_collect_cycles();

    $this->track = [
        'type' => 'FeatureCollection',
        'features' => [[
            'type' => 'Feature',
            'properties' => ['name' => 'Маршрут к роднику', 'desc' => 'Высота и время записаны в GPX'],
            'geometry' => [
                'type' => 'LineString',
                'coordinates' => [[37.123456789, 55.987654321, 123.4, 1750000000], [37.2, 55.9, 120, 1750000060]],
            ],
        ]],
    ];
    $this->trackJson = json_encode($this->track, JSON_THROW_ON_ERROR);
    $this->trackHash = hash('sha256', $this->trackJson);
    $this->payload = ['hash' => $this->trackHash, 'track' => $this->trackJson];
});

test('a guest can store a track without forging its uploader or leaking geometry in the upload response', function () {
    $otherUser = User::factory()->create();

    $response = $this->postJson(route('tracks.store'), $this->payload + ['user_id' => $otherUser->id]);

    $response->assertCreated();
    $track = Track::query()->sole();
    $response->assertJsonPath('id', $track->id)->assertJsonPath('hash', $this->trackHash)
        ->assertJsonPath('name', 'Маршрут к роднику')->assertJsonMissingPath('track');
    expect($response->json('token'))->toMatch('/\A[a-zA-Z0-9]{10}\z/');

    expect($track->user_id)->toBeNull()
        ->and($track->track)->toEqual(json_decode($this->trackJson));
});

test('track uploads record only the authenticated session user', function () {
    $uploader = User::factory()->create();
    $otherUser = User::factory()->create();

    $this->actingAs($uploader)
        ->postJson(route('tracks.store'), $this->payload + ['user_id' => $otherUser->id])
        ->assertCreated();

    expect(Track::query()->sole()->user->is($uploader))->toBeTrue();
});

test('public track downloads restore precise coordinates elevation time and GPX metadata', function () {
    $created = $this->actingAs(User::factory()->create())
        ->postJson(route('tracks.store'), $this->payload)->assertCreated();

    auth()->logout();

    $this->getJson(route('tracks.show', ['token' => $created->json('token')]))
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertExactJson([
            'id' => $created->json('id'),
            'hash' => $this->trackHash,
            'token' => $created->json('token'),
            'name' => 'Маршрут к роднику',
            'track' => $this->track,
        ]);
});

test('duplicate track uploads preserve geometry with independent owner references', function (bool $guestFirst) {
    $firstUploader = $guestFirst ? null : User::factory()->create();

    if ($firstUploader !== null) {
        $this->actingAs($firstUploader);
    }

    $created = $this->postJson(route('tracks.store'), $this->payload)->assertCreated();

    $this->actingAs(User::factory()->create())
        ->postJson(route('tracks.store'), $this->payload)
        ->assertCreated()
        ->assertJsonPath('id', fn (int $id): bool => $id !== $created->json('id'))->assertJsonPath('hash', $this->trackHash)
        ->assertJsonPath('token', fn (string $token): bool => $token !== $created->json('token'));
    $this->assertDatabaseCount('tracks', 2);

    expect(Track::query()->findOrFail($created->json('id'))->user_id)->toBe($firstUploader?->id);
})->with(['guest first' => true, 'authenticated first' => false]);

test('deleting the uploader preserves a shared track', function () {
    $uploader = User::factory()->create();
    $track = Track::factory()->for($uploader)->create();

    $uploader->delete();

    expect($track->fresh()->user_id)->toBeNull();
    $this->getJson(route('tracks.show', ['token' => $track->token]))
        ->assertOk()
        ->assertJsonPath('hash', $track->hash);
});

test('map track creation respects its policy', function () {
    $this->actingAs(User::factory()->create());
    Gate::before(fn (User $user, string $ability): ?bool => $ability === 'create' ? false : null);

    $this->postJson(route('tracks.store'), $this->payload)->assertForbidden();

    $this->assertDatabaseEmpty('tracks');
});

test('track lookups reject missing tokens and old content hashes', function (string $hash) {
    $this->getJson('/tracks/'.$hash)->assertNotFound();
})->with([
    'missing track' => 'Missing123',
    'old hash' => str_repeat('a', 64),
    'short hash' => 'abc123',
    'uppercase hash' => str_repeat('A', 64),
    'non hexadecimal hash' => str_repeat('z', 64),
]);

test('map track downloads use a separate rate limit from uploads', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.32']);

    for ($lookup = 0; $lookup < 31; $lookup++) {
        $this->getJson(route('tracks.show', ['token' => 'Missing123']))->assertNotFound();
    }

    $this->postJson(route('tracks.store'), $this->payload)->assertCreated();
});

test('track uploads require a serialized track and lowercase SHA256 hash', function (array $payload, array $fields) {
    $this->postJson(route('tracks.store'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($fields);

    $this->assertDatabaseEmpty('tracks');
})->with([
    'missing fields' => [[], ['hash', 'track']],
    'missing track' => [['hash' => str_repeat('a', 64)], ['track']],
    'missing hash' => [['track' => '{}'], ['hash']],
    'short hash' => [['hash' => 'abc', 'track' => '{}'], ['hash']],
    'uppercase hash' => [['hash' => str_repeat('A', 64), 'track' => '{}'], ['hash']],
    'non hexadecimal hash' => [['hash' => str_repeat('z', 64), 'track' => '{}'], ['hash']],
    'numeric hash' => [['hash' => 123, 'track' => '{}'], ['hash']],
    'decoded instead of serialized track' => [['hash' => str_repeat('a', 64), 'track' => ['type' => 'FeatureCollection']], ['track']],
]);

test('a forged track hash is rejected even when that hash is already stored', function (bool $existing) {
    if ($existing) {
        $this->postJson(route('tracks.store'), $this->payload)->assertCreated();
    }

    $changedTrack = $this->track;
    $changedTrack['features'][0]['geometry']['coordinates'][0] = [37, 55];

    $this->postJson(route('tracks.store'), [
        'hash' => $this->trackHash,
        'track' => json_encode($changedTrack, JSON_THROW_ON_ERROR),
    ])->assertUnprocessable()->assertJsonValidationErrors('hash');

    $this->assertDatabaseCount('tracks', $existing ? 1 : 0);

    if ($existing) {
        expect(Track::query()->sole()->track)->toEqual(json_decode($this->trackJson));
    }
})->with(['new hash' => false, 'existing hash' => true]);

test('track hash verification preserves exact JSON serialization and exterior whitespace', function () {
    $serialized = "\n\t ".json_encode($this->track, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)." \r\n";
    $hash = hash('sha256', $serialized);

    expect($hash)->not->toBe($this->trackHash);

    $this->postJson(route('tracks.store'), ['hash' => $hash, 'track' => $serialized])
        ->assertCreated()
        ->assertJsonPath('hash', $hash);

    expect(Track::query()->sole()->track)->toEqual(json_decode($this->trackJson));
});

test('tracks preserve multiple segments waypoints scalar metadata and empty property objects', function () {
    $track = [
        'type' => 'FeatureCollection',
        'features' => [
            [
                'type' => 'Feature',
                'properties' => ['name' => 'Route', 'number' => 5, 'desc' => '<img src="https://example.test/photo">', 'link' => 'https://example.test', 'selected' => true, 'optional' => null],
                'geometry' => ['type' => 'MultiLineString', 'coordinates' => [[[0, 0], [1, 1]], [[3, 3, 100], [4, 4, 110]]]],
            ],
            ['type' => 'Feature', 'id' => 'waypoint-1', 'properties' => (object) [], 'geometry' => ['type' => 'Point', 'coordinates' => [4, 4, 110, 1750000060]]],
            ['type' => 'Feature', 'id' => 2, 'properties' => null, 'geometry' => ['type' => 'Point', 'coordinates' => [-180, -90]]],
        ],
    ];
    $serialized = json_encode($track, JSON_THROW_ON_ERROR);
    $hash = hash('sha256', $serialized);

    $created = $this->postJson(route('tracks.store'), ['hash' => $hash, 'track' => $serialized])->assertCreated();

    $response = $this->getJson(route('tracks.show', ['token' => $created->json('token')]))->assertOk();
    $restored = json_decode($response->getContent(), false, 32, JSON_THROW_ON_ERROR)->track;

    expect($restored)->toEqual(json_decode($serialized))
        ->and($restored->features[1]->properties)->toBeInstanceOf(stdClass::class);
});

test('point-only tracks with valid coordinate dimensions are accepted', function (array $position) {
    $track = [
        'type' => 'FeatureCollection',
        'features' => [['type' => 'Feature', 'properties' => null, 'geometry' => ['type' => 'Point', 'coordinates' => $position]]],
    ];
    $serialized = json_encode($track, JSON_THROW_ON_ERROR);
    $hash = hash('sha256', $serialized);

    $created = $this->postJson(route('tracks.store'), ['hash' => $hash, 'track' => $serialized])->assertCreated();
    $this->getJson(route('tracks.show', ['token' => $created->json('token')]))
        ->assertOk()->assertJsonPath('track.features.0.geometry.coordinates', $position);
})->with([
    'positive bounds' => [[180, 90]],
    'negative bounds' => [[-180, -90]],
    'elevation' => [[37, 55, -15]],
    'elevation and time' => [[37, 55, 0, 1750000060]],
    'time without elevation' => [[37, 55, null, 1750000060]],
]);

test('invalid GeoJSON structures are rejected before storing a track', function (string $serialized) {
    $this->postJson(route('tracks.store'), [
        'hash' => hash('sha256', $serialized),
        'track' => $serialized,
    ])->assertUnprocessable()->assertJsonValidationErrors('track');

    $this->assertDatabaseEmpty('tracks');
})->with([
    'malformed JSON' => '{',
    'null root' => 'null',
    'array root' => '[]',
    'missing features' => '{"type":"FeatureCollection"}',
    'empty features' => '{"type":"FeatureCollection","features":[]}',
    'feature object instead of list' => '{"type":"FeatureCollection","features":{"0":{"type":"Feature","properties":null,"geometry":{"type":"Point","coordinates":[0,0]}}}}',
    'foreign root fields' => '{"type":"FeatureCollection","features":[],"url":"https://example.test/track"}',
    'non feature member' => '{"type":"FeatureCollection","features":[{"type":"Point","coordinates":[0,0]}]}',
    'null geometry' => '{"type":"FeatureCollection","features":[{"type":"Feature","properties":null,"geometry":null}]}',
    'polygon geometry' => '{"type":"FeatureCollection","features":[{"type":"Feature","properties":null,"geometry":{"type":"Polygon","coordinates":[[[0,0],[1,0],[1,1],[0,0]]]}}]}',
    'geometry collection' => '{"type":"FeatureCollection","features":[{"type":"Feature","properties":null,"geometry":{"type":"GeometryCollection","geometries":[]}}]}',
    'empty line' => '{"type":"FeatureCollection","features":[{"type":"Feature","properties":null,"geometry":{"type":"LineString","coordinates":[]}}]}',
    'single position line' => '{"type":"FeatureCollection","features":[{"type":"Feature","properties":null,"geometry":{"type":"LineString","coordinates":[[0,0]]}}]}',
    'empty multiline' => '{"type":"FeatureCollection","features":[{"type":"Feature","properties":null,"geometry":{"type":"MultiLineString","coordinates":[]}}]}',
    'empty multiline segment' => '{"type":"FeatureCollection","features":[{"type":"Feature","properties":null,"geometry":{"type":"MultiLineString","coordinates":[[[0,0],[1,1]],[]]}}]}',
    'foreign geometry fields' => '{"type":"FeatureCollection","features":[{"type":"Feature","properties":null,"geometry":{"type":"Point","coordinates":[0,0],"crs":"EPSG:3857"}}]}',
    'foreign feature fields' => '{"type":"FeatureCollection","features":[{"type":"Feature","properties":null,"url":"https://example.test","geometry":{"type":"Point","coordinates":[0,0]}}]}',
    'missing properties' => '{"type":"FeatureCollection","features":[{"type":"Feature","geometry":{"type":"Point","coordinates":[0,0]}}]}',
    'array properties' => '{"type":"FeatureCollection","features":[{"type":"Feature","properties":[],"geometry":{"type":"Point","coordinates":[0,0]}}]}',
    'nested properties' => '{"type":"FeatureCollection","features":[{"type":"Feature","properties":{"nested":{"name":"track"}},"geometry":{"type":"Point","coordinates":[0,0]}}]}',
    'array property values' => '{"type":"FeatureCollection","features":[{"type":"Feature","properties":{"values":[1,2]},"geometry":{"type":"Point","coordinates":[0,0]}}]}',
    'non finite property' => '{"type":"FeatureCollection","features":[{"type":"Feature","properties":{"number":1e400},"geometry":{"type":"Point","coordinates":[0,0]}}]}',
    'invalid feature id' => '{"type":"FeatureCollection","features":[{"type":"Feature","id":false,"properties":null,"geometry":{"type":"Point","coordinates":[0,0]}}]}',
    'too deeply nested JSON' => str_repeat('[', 33).'0'.str_repeat(']', 33),
]);

test('invalid positions are rejected even inside later track segments', function (string $position) {
    $serialized = '{"type":"FeatureCollection","features":[{"type":"Feature","properties":null,"geometry":{"type":"MultiLineString","coordinates":[[[0,0],[1,1]],[[1,1],'.$position.']]}}]}';

    $this->postJson(route('tracks.store'), [
        'hash' => hash('sha256', $serialized),
        'track' => $serialized,
    ])->assertUnprocessable()->assertJsonValidationErrors('track');

    $this->assertDatabaseEmpty('tracks');
})->with([
    'longitude above range' => '[180.1,0]',
    'longitude below range' => '[-180.1,0]',
    'latitude above range' => '[0,90.1]',
    'latitude below range' => '[0,-90.1]',
    'numeric string' => '["0",0]',
    'boolean' => '[false,0]',
    'null coordinate' => '[0,null]',
    'infinite longitude' => '[1e400,0]',
    'infinite altitude' => '[0,0,1e400]',
    'infinite time' => '[0,0,0,1e400]',
    'null altitude' => '[0,0,null]',
    'null timestamp' => '[0,0,0,null]',
    'null altitude and timestamp' => '[0,0,null,null]',
    'string time' => '[0,0,0,"2026-01-01"]',
    'one dimension' => '[0]',
    'five dimensions' => '[0,0,0,0,0]',
    'coordinate object' => '{"0":0,"1":0}',
]);

test('track byte size is bounded before decoding including multibyte text', function () {
    $track = $this->track;
    $track['features'][0]['properties']['desc'] = str_repeat('я', (int) (GeoJsonTrackRule::MAX_BYTES / 2));
    $serialized = json_encode($track, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    unset($track);

    expect(mb_strlen($serialized))->toBeLessThan(GeoJsonTrackRule::MAX_BYTES)
        ->and(mb_strlen($serialized, '8bit'))->toBeGreaterThan(GeoJsonTrackRule::MAX_BYTES);

    $this->postJson(route('tracks.store'), [
        'hash' => hash('sha256', $serialized),
        'track' => $serialized,
    ], options: JSON_UNESCAPED_UNICODE)->assertUnprocessable()->assertJsonValidationErrors('track');

    $this->assertDatabaseEmpty('tracks');
});

test('track coordinate limits cover the entire collection', function () {
    $line = '{"type":"Feature","properties":null,"geometry":{"type":"LineString","coordinates":['.str_repeat('[0,0],', (int) (GeoJsonTrackRule::MAX_COORDINATES / 2) - 1).'[1,1]]}}';
    $point = '{"type":"Feature","properties":null,"geometry":{"type":"Point","coordinates":[1,1]}}';
    $serialized = '{"type":"FeatureCollection","features":['.$line.','.$line.','.$point.']}';

    $this->postJson(route('tracks.store'), [
        'hash' => hash('sha256', $serialized),
        'track' => $serialized,
    ])->assertUnprocessable()->assertJsonValidationErrors('track');

    $this->assertDatabaseEmpty('tracks');
});

test('tracks with too many waypoint features are rejected', function () {
    $point = '{"type":"Feature","properties":null,"geometry":{"type":"Point","coordinates":[1,1]}}';
    $serialized = '{"type":"FeatureCollection","features":['.str_repeat($point.',', GeoJsonTrackRule::MAX_FEATURES).$point.']}';

    $this->postJson(route('tracks.store'), [
        'hash' => hash('sha256', $serialized),
        'track' => $serialized,
    ])->assertUnprocessable()->assertJsonValidationErrors('track');

    $this->assertDatabaseEmpty('tracks');
});

test('a long track is stored and downloaded without text column truncation', function () {
    $track = $this->track;
    $track['features'][0]['geometry']['coordinates'] = array_fill(0, 12000, [37.123456789, 55.987654321, 123.4, 1750000000]);
    $serialized = json_encode($track, JSON_THROW_ON_ERROR);
    $hash = hash('sha256', $serialized);

    expect(mb_strlen($serialized))->toBeGreaterThan(65535);

    $created = $this->postJson(route('tracks.store'), ['hash' => $hash, 'track' => $serialized])->assertCreated();
    $this->getJson(route('tracks.show', ['token' => $created->json('token')]))
        ->assertOk()->assertJsonPath('track.features.0.geometry.coordinates', $track['features'][0]['geometry']['coordinates']);
});
