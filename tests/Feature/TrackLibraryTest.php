<?php

declare(strict_types=1);

use App\Actions\StoreMapAction;
use App\Models\Map;
use App\Models\Track;
use App\Models\User;
use App\Support\MapPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

test('maps and tracks use only the agreed schema', function () {
    expect(Schema::getColumnListing('tracks'))->toEqualCanonicalizing([
        'id', 'user_id', 'token', 'name', 'hash', 'track', 'summary', 'created_at', 'updated_at',
    ])->and(Schema::getColumnListing('maps'))->toEqualCanonicalizing([
        'id', 'user_id', 'slug', 'title', 'state', 'track_id', 'version', 'views_count', 'is_starred', 'created_at', 'updated_at',
    ])->and(Schema::hasTable('track_uploads'))->toBeFalse();
});

function trackLibraryPayload(): array
{
    $geometry = json_encode(['type' => 'FeatureCollection', 'features' => [[
        'type' => 'Feature', 'properties' => ['name' => 'River & hills'],
        'geometry' => ['type' => 'LineString', 'coordinates' => [[37.1, 55.1, 123, 1750000000], [37.2, 55.2, 124, 1750000060]]],
    ]]], JSON_THROW_ON_ERROR);

    return ['hash' => hash('sha256', $geometry), 'track' => $geometry, 'name' => 'Sunday route'];
}

test('guests upload public short tracks but have no library and cannot delete them', function () {
    $created = $this->postJson(route('tracks.store'), trackLibraryPayload())->assertCreated();
    $token = $created->json('token');
    expect($token)->toMatch('/\A[a-zA-Z0-9]{10}\z/');
    $this->getJson(route('tracks.show', ['token' => $token]))->assertOk()->assertJsonPath('token', $token)
        ->assertJsonPath('name', 'Sunday route')->assertJsonPath('track.features.0.geometry.coordinates.0.2', 123);
    $this->getJson(route('tracks.options'))->assertUnauthorized();
    $this->deleteJson(route('tracks.destroy', ['track' => $token]))->assertUnauthorized();
    $this->actingAs(User::factory()->create())->deleteJson(route('tracks.destroy', ['track' => $token]))->assertForbidden();
    $this->assertDatabaseEmpty('maps');
});

test('reuploading identical geometry deduplicates within each owners library only', function () {
    $owner = User::factory()->create();
    $first = $this->actingAs($owner)->postJson(route('tracks.store'), trackLibraryPayload())->assertCreated();
    $this->postJson(route('tracks.store'), trackLibraryPayload())->assertOk()->assertJsonPath('token', $first->json('token'));
    $second = $this->actingAs(User::factory()->create())->postJson(route('tracks.store'), trackLibraryPayload())->assertCreated();
    expect($second->json('token'))->not->toBe($first->json('token'));
    $this->assertDatabaseCount('tracks', 2);
    $this->actingAs($owner)->deleteJson(route('tracks.destroy', ['track' => $first->json('token')]))->assertNoContent();
    $this->getJson('/tracks/'.$first->json('token'))->assertNotFound();
    $this->getJson('/tracks/'.$second->json('token'))->assertOk();
});

test('guest uploads of identical geometry get independent public links', function () {
    $first = $this->postJson(route('tracks.store'), trackLibraryPayload())->assertCreated();
    $second = $this->postJson(route('tracks.store'), trackLibraryPayload())->assertCreated();

    expect($second->json('token'))->not->toBe($first->json('token'))
        ->and($second->json('id'))->not->toBe($first->json('id'));
    $this->assertDatabaseCount('tracks', 2);
    $this->getJson('/tracks/'.$first->json('token'))->assertOk();
    $this->getJson('/tracks/'.$second->json('token'))->assertOk();
});

test('track library is private searchable paginated and exposes summaries without geometry', function (string $prefix) {
    $owner = User::factory()->create();
    $own = Track::factory()->for($owner)->create(['name' => 'My coastal route']);
    $foreign = Track::factory()->for(User::factory())->create(['name' => 'My coastal private name']);
    $ownedMap = Map::factory()->for($owner)->create(['title' => 'Sunday', 'track_id' => $own->id]);
    Map::factory()->for(User::factory())->create(['title' => 'Someone else title', 'track_id' => $own->id]);
    $response = $this->actingAs($owner)->getJson($prefix.'/user/tracks/options?q=coastal')->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.token', $own->token)->assertJsonPath('data.0.map_count', 2)
        ->assertJsonPath('data.0.maps', [['id' => $ownedMap->id, 'title' => 'Sunday']])
        ->assertJsonPath('data.0.other_maps_count', 1)->assertJsonMissingPath('data.0.track');
    expect($response->getContent())->not->toContain($foreign->token)->not->toContain('Someone else title');
    $response->assertJsonPath('data.0.delete_url', url($prefix.'/user/tracks/'.$own->token));
    $this->get($prefix.'/user/tracks')->assertOk()->assertViewHas('activeTab', 'tracks');
})->with(['', '/ru']);

test('track deletion removes it from every saved view keeps map links alive and rejects stale updates', function () {
    $owner = User::factory()->create();
    $upload = Track::factory()->for($owner)->create();
    $state = app(MapPayload::class)->defaultState();
    $state['filters']['along'] = true;
    $created = $this->actingAs($owner)->postJson(route('maps.store'), ['title' => 'Weekend', 'state' => $state, 'track_token' => $upload->token])->assertCreated()
        ->assertJsonPath('track.token', $upload->token)->assertJsonPath('track.name', $upload->name);
    $map = Map::query()->sole();
    $this->deleteJson(route('tracks.destroy', $upload))->assertNoContent();
    $this->getJson($created->json('url'))->assertOk()->assertJsonPath('track', null)->assertJsonMissingPath('track_deleted')
        ->assertJsonPath('state.filters.along', false)->assertJsonPath('version', 2);
    $this->getJson('/tracks/'.$upload->token)->assertNotFound();
    $this->patchJson(route('maps.update', $map), ['state' => $state, 'track_token' => $upload->token, 'version' => 1])->assertUnprocessable();
    $this->patchJson(route('maps.update', $map), ['state' => $state, 'track_token' => null, 'version' => 1])->assertConflict();
    $this->assertModelExists($map);
    $this->assertModelMissing($upload);
});

test('deleting a saved map keeps its uploaded track in the library', function () {
    $owner = User::factory()->create();
    $upload = Track::factory()->for($owner)->create();
    $map = Map::factory()->for($owner)->create(['track_id' => $upload->id]);
    $this->actingAs($owner)->deleteJson(route('maps.destroy', ['map' => $map->id]))->assertNoContent();
    $this->getJson('/tracks/'.$upload->token)->assertOk();
    $this->getJson(route('tracks.options'))->assertJsonPath('data.0.map_count', 0);
});

test('track renaming and downloads require ownership and preserve metadata and geometry', function () {
    $owner = User::factory()->create();
    $created = $this->actingAs($owner)->postJson(route('tracks.store'), trackLibraryPayload())->assertCreated();
    $upload = Track::query()->sole();
    $this->patchJson(route('tracks.update', $upload), ['name' => ' River < route ', 'user_id' => 99])->assertOk()->assertJsonPath('name', 'River < route');
    $download = $this->get(route('tracks.download', $upload))->assertOk()->assertHeader('Content-Type', 'application/gpx+xml; charset=UTF-8');
    $xml = simplexml_load_string($download->getContent());
    expect((string) $xml->metadata->name)->toBe('River < route')
        ->and((string) $xml->trk->name)->toBe('River & hills')
        ->and((string) $xml->trk->trkseg->trkpt[0]['lon'])->toBe('37.1')
        ->and((string) $xml->trk->trkseg->trkpt[0]->ele)->toBe('123');
    $this->patchJson(route('tracks.update', $upload), ['name' => ' '])->assertUnprocessable()->assertJsonValidationErrors('name');
    $this->actingAs(User::factory()->create())->patchJson(route('tracks.update', $upload), ['name' => 'Other'])->assertForbidden();
    $this->get(route('tracks.download', $upload))->assertForbidden();
});

test('a track deleted after validation cannot be attached to a saved map', function () {
    $owner = User::factory()->create();
    $upload = Track::factory()->for($owner)->create();
    $action = app(StoreMapAction::class);
    $validated = $action->validate(['title' => 'Sunday springs', 'state' => app(MapPayload::class)->defaultState(), 'track_token' => $upload->token]);
    $upload->delete();
    expect(fn () => $action->execute($owner, $validated))->toThrow(ValidationException::class);
    $this->assertDatabaseEmpty('maps');
});

test('track upload and GPX download preserve timestamps without inventing elevation', function () {
    $geometry = json_encode(['type' => 'FeatureCollection', 'features' => [[
        'type' => 'Feature', 'properties' => ['name' => 'Timed route'],
        'geometry' => ['type' => 'LineString', 'coordinates' => [[37.1, 55.1, null, 1750000000], [37.2, 55.2, null, 1750000060]]],
    ]]], JSON_THROW_ON_ERROR);
    $owner = User::factory()->create();
    $created = $this->actingAs($owner)->postJson(route('tracks.store'), [
        'hash' => hash('sha256', $geometry), 'track' => $geometry,
    ])->assertCreated();
    $this->getJson(route('tracks.show', ['token' => $created->json('token')]))->assertOk()
        ->assertJsonPath('track.features.0.geometry.coordinates.0', [37.1, 55.1, null, 1750000000]);
    $download = $this->get(route('tracks.download', Track::query()->sole()))->assertOk();
    $xml = simplexml_load_string($download->getContent());

    expect($xml->trk->trkseg->trkpt[0]->ele->count())->toBe(0)
        ->and((string) $xml->trk->trkseg->trkpt[0]->time)->toBe(gmdate('Y-m-d\TH:i:s\Z', 1750000000))
        ->and((string) $xml->trk->trkseg->trkpt[1]->time)->toBe(gmdate('Y-m-d\TH:i:s\Z', 1750000060));
});

test('track tokens are case sensitive and content hashes cannot open tracks', function () {
    $upper = Track::factory()->create(['token' => 'AbCd123456']);
    $lower = Track::factory()->create(['token' => 'abcd123456']);
    $this->getJson('/tracks/'.$upper->token)->assertOk()->assertJsonPath('hash', $upper->hash);
    $this->getJson('/tracks/'.$lower->token)->assertOk()->assertJsonPath('hash', $lower->hash);
    $legacy = Track::factory()->create();
    $this->getJson('/tracks/'.$legacy->hash)->assertNotFound();
});

test('independent copies of identical geometry keep their own links when one owner deletes a track', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $track = Track::factory()->for($owner)->create();
    $independent = Track::factory()->for($other)->create(['track' => $track->track, 'hash' => $track->hash]);
    $guestTrack = Track::factory()->create(['track' => $track->track, 'hash' => $track->hash]);
    $own = Map::factory()->for($owner)->create(['track_id' => $track->id]);
    $copy = Map::factory()->for($other)->create(['track_id' => $independent->id]);
    $guest = Map::factory()->create(['track_id' => $guestTrack->id]);

    $this->actingAs($owner)->deleteJson(route('tracks.destroy', $track))->assertNoContent();

    $this->getJson(route('maps.show', $own))->assertOk()->assertJsonPath('track', null);
    $this->getJson(route('maps.show', $copy))->assertOk()->assertJsonPath('track.token', $independent->token);
    $this->getJson(route('maps.show', $guest))->assertOk()->assertJsonPath('track.token', $guestTrack->token);
    $this->getJson('/tracks/'.$track->token)->assertNotFound();
    $this->getJson('/tracks/'.$independent->token)->assertOk();
    $this->getJson('/tracks/'.$guestTrack->token)->assertOk();
    $this->assertDatabaseCount('tracks', 2);
});

test('track deletion detaches saved maps owned by other users without deleting those maps', function () {
    $owner = User::factory()->create();
    $upload = Track::factory()->for($owner)->create();
    $other = User::factory()->create();
    $copy = Map::factory()->for($other)->create(['track_id' => $upload->id]);
    $this->actingAs($owner)->deleteJson(route('tracks.destroy', $upload))->assertNoContent();
    $this->actingAs($other)->getJson(route('maps.show', $copy))->assertOk()->assertJsonMissingPath('track_deleted')->assertJsonPath('track', null);
    $this->getJson(route('maps.options'))->assertJsonPath('data.0.track', null)->assertJsonMissingPath('data.0.track_deleted');
    $this->assertModelExists($copy);
});

test('short track token collisions retry without changing the existing upload', function () {
    $existing = Track::factory()->create(['token' => 'Aa12345678']);
    $tokens = ['Aa12345678', 'Zz12345678'];
    Illuminate\Support\Str::createRandomStringsUsing(function (int $length) use (&$tokens): string {
        return $length === 10 ? array_shift($tokens) : str_repeat('x', $length);
    });
    try {
        $this->postJson(route('tracks.store'), trackLibraryPayload())->assertCreated()->assertJsonPath('token', 'Zz12345678');
        $this->assertModelExists($existing);
        $this->assertDatabaseCount('tracks', 2);
    } finally {
        Illuminate\Support\Str::createRandomStringsNormally();
    }
});

test('deleting a track detaches every map across batches without changing view counts or favorites', function () {
    $owner = User::factory()->create();
    $track = Track::factory()->for($owner)->create();
    $state = app(MapPayload::class)->defaultState();
    $state['filters']['along'] = true;
    Map::factory()->count(102)->for($owner)->create([
        'track_id' => $track->id,
        'state' => $state,
        'version' => 5,
        'views_count' => 13,
        'is_starred' => true,
    ]);

    $this->actingAs($owner)->deleteJson(route('tracks.destroy', $track))->assertNoContent();

    expect(Map::query()->count())->toBe(102)
        ->and(Map::query()->whereNotNull('track_id')->count())->toBe(0)
        ->and(Map::query()->where('state->filters->along', true)->count())->toBe(0)
        ->and(Map::query()->where('version', 6)->count())->toBe(102)
        ->and(Map::query()->where('views_count', 13)->count())->toBe(102)
        ->and(Map::query()->where('is_starred', true)->count())->toBe(102);
    $this->assertModelMissing($track);
});
