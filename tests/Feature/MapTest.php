<?php

declare(strict_types=1);

use App\Actions\RenameMapAction;
use App\Actions\StoreMapAction;
use App\Actions\UpdateMapAction;
use App\Models\Map;
use App\Models\Track;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->payload = [
        'title' => 'Маршрут к источникам',
        'track_token' => null,
        'state' => [
            'version' => 1,
            'center' => [-122.4194, 37.7749],
            'zoom' => 14.75,
            'sourceName' => 'satellite',
            'filters' => [
                'spring' => true,
                'water_well' => false,
                'water_tap' => true,
                'drinking_water' => false,
                'fountain' => true,
                'other' => false,
                'with_reports' => true,
                'along' => false,
            ],
            'overlays' => ['stravaPublic' => true, 'osmTraces' => false],
            'page' => ['spring' => null, 'user' => null, 'location' => null],
            'fullscreen' => true,
            'minimized' => false,
        ],
    ];
});

afterEach(function () {
    Str::createRandomStringsNormally();
});

test('guests cannot create saved maps', function () {
    $forgedOwner = User::factory()->create();
    $this->postJson(route('maps.store'), $this->payload + ['user_id' => $forgedOwner->id])->assertUnauthorized();
    expect(fn () => app(StoreMapAction::class)(null, $this->payload))->toThrow(AuthorizationException::class);
    $this->assertDatabaseEmpty('maps');
});

test('an authenticated map belongs to the current user regardless of submitted ownership', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($owner)->postJson(route('maps.store'), $this->payload + ['user_id' => $other->id])
        ->assertCreated()
        ->assertJsonPath('can_update', true);

    expect(Map::query()->sole()->user_id)->toBe($owner->id);
});

test('sharing while signed in creates an owned snapshot in my maps', function () {
    $owner = User::factory()->create();
    $track = Track::factory()->create();
    $this->payload['track_token'] = $track->token;

    $response = $this->actingAs($owner)->postJson(route('maps.store'), $this->payload + [
        'user_id' => $owner->id,
        'description' => 'Do not save these details',
        'slug' => 'sunday-springs',
    ])->assertCreated()->assertJsonPath('can_update', true)->assertJsonMissingPath('description');
    $map = Map::query()->sole();

    $response->assertJsonPath('url', route('maps.show', $map).'/')
        ->assertJsonPath('slug', 'sunday-springs')->assertJsonMissingPath('token')
        ->assertJsonPath('track.hash', $track->hash);
    expect($map->user_id)->toBe($owner->id)
        ->and($map->title)->toBe($this->payload['title'])
        ->and($map->state)->toEqual($this->payload['state'])
        ->and($owner->maps()->count())->toBe(1);

    $this->patchJson(route('maps.details', $map), ['title' => 'Changed', 'version' => 1])->assertOk();
    $this->patchJson(route('maps.update', $map), $this->payload + ['version' => 2])->assertOk();
    expect($map->refresh()->version)->toBe(3);
});

test('shared snapshot URLs use the current page language even for signed in visitors', function (string $locale, string $prefix) {
    $this->travelTo(now()->setDate(2026, 9, 22)->setTime(12, 0));
    $this->actingAs(User::factory()->create(['locale' => $locale === 'ru' ? 'en' : 'ru']))
        ->withHeader('X-Rodnik-Locale', $locale)
        ->postJson(route('maps.store'), $this->payload)
        ->assertCreated()
        ->assertJsonPath('title', $this->payload['title'])
        ->assertJsonPath('url', fn (string $url): bool => preg_match('#^'.preg_quote(url($prefix.'/maps/'), '#').'/[a-z0-9]{8}/$#', $url) === 1)
        ->assertJsonPath('can_update', true);
})->with(['English' => ['en', ''], 'Russian' => ['ru', '/ru']]);

test('map creation requires an explicit map view', function () {
    $this->actingAs(User::factory()->create())->postJson(route('maps.store'), ['title' => 'Sunday springs', 'track_token' => null])
        ->assertUnprocessable()->assertJsonValidationErrors('state');
    $this->actingAs(User::factory()->create())->postJson(route('maps.store'), ['title' => 'Lycian Way', 'slug' => 'lycian-way', 'track_token' => null])
        ->assertUnprocessable()->assertJsonValidationErrors('state');
    $this->assertDatabaseEmpty('maps');
});

test('created and updated share links follow the current page language over account preferences', function (string $locale, string $prefix) {
    $this->travelTo(now()->setDate(2026, 9, 22)->setTime(12, 0));
    $owner = User::factory()->create(['locale' => $locale === 'ru' ? 'en' : 'ru']);
    $this->actingAs($owner)->withHeader('X-Rodnik-Locale', $locale);

    $created = $this->postJson(route('maps.store'), $this->payload)
        ->assertCreated()->assertJsonPath('title', $this->payload['title']);
    $slug = $created->json('slug');
    $created->assertJsonPath('url', url($prefix.'/maps/'.$slug).'/')
        ->assertJsonPath('login_url', url($prefix.'/maps/'.$slug.'/login'));
    $this->patchJson(route('maps.update', $created->json('id')), $this->payload + ['version' => 1])
        ->assertOk()
        ->assertJsonPath('url', url($prefix.'/maps/'.$slug).'/')
        ->assertJsonPath('login_url', url($prefix.'/maps/'.$slug.'/login'));
})->with(['English' => ['en', ''], 'Russian' => ['ru', '/ru']]);

test('a saved map requires an explicit name and only authenticated users can create it', function (string $locale, bool $authenticated) {
    if ($authenticated) {
        $this->actingAs(User::factory()->create(['locale' => $locale === 'ru' ? 'en' : 'ru']));
    }
    unset($this->payload['title']);
    $this->withHeader('X-Rodnik-Locale', $locale);
    $response = $this->postJson(route('maps.store'), $this->payload);
    if ($authenticated) {
        $response->assertUnprocessable()->assertJsonPath('errors.title.0', __('ui.maps.title_required', locale: $locale));
    } else {
        $response->assertUnauthorized();
    }
    $this->assertDatabaseEmpty('maps');
})->with([
    'English guest' => ['en', false],
    'Russian guest' => ['ru', false],
    'English owner' => ['en', true],
    'Russian owner' => ['ru', true],
]);

test('the owner can supply a name after an unnamed save is rejected and later rename the map', function () {
    $owner = User::factory()->create();
    unset($this->payload['title']);
    $this->actingAs($owner)->postJson(route('maps.store'), $this->payload)->assertUnprocessable()->assertJsonValidationErrors('title');
    $this->payload['title'] = 'Saturday springs';
    $created = $this->postJson(route('maps.store'), $this->payload)->assertCreated();

    $this->patchJson(route('maps.rename', ['map' => $created->json('id')]), [
        'title' => 'Sunday springs',
        'version' => $created->json('version'),
    ])
        ->assertOk()
        ->assertJsonPath('title', 'Sunday springs')
        ->assertJsonPath('version', 2);

    expect($owner->maps()->sole()->title)->toBe('Sunday springs')
        ->and($owner->maps()->sole()->id)->toBe($created->json('id'));
});

test('renaming a map changes only its title revision and update timestamp', function () {
    $this->freezeTime();
    $owner = User::factory()->create();
    $track = Track::factory()->create();
    $map = Map::factory()->for($owner)->create([
        'title' => 'Old title',
        'track_id' => $track->id,
        'views_count' => 42,
        'version' => 7,
    ]);
    $original = $map->refresh()->getAttributes();
    $this->travel(1)->minutes();

    $response = $this->actingAs($owner)->patchJson(route('maps.rename', $map), [
        'title' => '  Sunday springs  ',
        'version' => 7,
        'state' => $this->payload['state'],
        'track_token' => null,
        'user_id' => User::factory()->create()->id,
        'token' => 'Changed1',
        'views_count' => 0,
    ])->assertOk();
    $map->refresh();

    $response->assertExactJson([
        'title' => 'Sunday springs',
        'version' => 8,
        'updated_at' => $map->updated_at->toISOString(),
    ]);
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
    expect($map->getAttributes())->toEqual([
        ...$original,
        'title' => 'Sunday springs',
        'version' => 8,
        'updated_at' => now()->toDateTimeString(),
    ]);
});

test('guests cannot rename even anonymous maps', function () {
    $map = Map::factory()->create(['title' => 'Original']);

    $this->patchJson(route('maps.rename', $map), ['title' => 'Changed', 'version' => 1])->assertUnauthorized();

    expect(fn () => app(RenameMapAction::class)(null, $map, ['title' => 'Changed', 'version' => 1]))
        ->toThrow(AuthorizationException::class);
    expect($map->refresh()->title)->toBe('Original');
});

test('other users cannot rename owned or anonymous maps', function (bool $anonymous) {
    $map = Map::factory()->create(['user_id' => $anonymous ? null : User::factory()->create()->id, 'title' => 'Original']);

    $this->actingAs(User::factory()->create())
        ->patchJson(route('maps.rename', $map), ['title' => 'Changed', 'version' => 1])
        ->assertForbidden();

    expect($map->refresh()->title)->toBe('Original')
        ->and($map->version)->toBe(1);
})->with([true, false]);

test('map renames reject invalid titles and revisions', function (string $field, mixed $value) {
    $owner = User::factory()->create();
    $map = Map::factory()->for($owner)->create(['title' => 'Original']);
    $payload = ['title' => 'New title', 'version' => 1];
    $payload[$field] = $value;

    $this->actingAs($owner)->patchJson(route('maps.rename', $map), $payload)
        ->assertUnprocessable()->assertJsonValidationErrors($field);

    expect($map->refresh()->title)->toBe('Original')
        ->and($map->version)->toBe(1);
})->with([
    'null title' => ['title', null],
    'empty title' => ['title', ''],
    'blank title' => ['title', '   '],
    'numeric title' => ['title', 42],
    'long title' => ['title', str_repeat('я', 161)],
    'null revision' => ['version', null],
    'zero revision' => ['version', 0],
    'negative revision' => ['version', -1],
    'string revision' => ['version', '1'],
    'boolean revision' => ['version', true],
    'fractional revision' => ['version', 1.5],
    'overflow revision' => ['version', 4294967295],
]);

test('map renames require both title and revision', function (string $field) {
    $owner = User::factory()->create();
    $map = Map::factory()->for($owner)->create();
    $payload = ['title' => 'New title', 'version' => 1];
    unset($payload[$field]);

    $this->actingAs($owner)->patchJson(route('maps.rename', $map), $payload)
        ->assertUnprocessable()->assertJsonValidationErrors($field);

    expect($map->refresh()->version)->toBe(1);
})->with(['title', 'version']);

test('renaming and full snapshot saves cannot overwrite a newer revision', function (bool $renameFirst) {
    $owner = User::factory()->create();
    $map = Map::factory()->for($owner)->create(['title' => 'Original']);
    $renameUrl = route('maps.rename', $map);
    $updateUrl = route('maps.update', $map);
    $renamePayload = ['title' => 'Renamed map', 'version' => 1];
    $updatePayload = $this->payload + ['version' => 1];
    $this->actingAs($owner);

    $this->patchJson($renameFirst ? $renameUrl : $updateUrl, $renameFirst ? $renamePayload : $updatePayload)
        ->assertOk()->assertJsonPath('version', 2);
    $saved = $map->refresh()->getAttributes();

    $this->patchJson($renameFirst ? $updateUrl : $renameUrl, $renameFirst ? $updatePayload : $renamePayload)
        ->assertConflict();

    expect($map->refresh()->getAttributes())->toEqual($saved);
})->with(['rename first' => true, 'snapshot first' => false]);

test('a stale rename cannot overwrite a newer name', function () {
    $owner = User::factory()->create();
    $map = Map::factory()->for($owner)->create();
    $url = route('maps.rename', $map);

    $this->actingAs($owner)->patchJson($url, ['title' => 'Current name', 'version' => 1])->assertOk();
    $this->patchJson($url, ['title' => 'Stale name', 'version' => 1])->assertConflict();

    expect($map->refresh()->title)->toBe('Current name')
        ->and($map->version)->toBe(2);
});

test('updating without a title does not reset an existing map name', function () {
    $owner = User::factory()->create();
    $map = Map::factory()->for($owner)->create(['title' => 'Sunday springs']);
    unset($this->payload['title']);

    $this->actingAs($owner)->patchJson(route('maps.update', $map), $this->payload + ['version' => 1])
        ->assertOk()
        ->assertJsonPath('title', 'Sunday springs');

    expect($map->refresh()->title)->toBe('Sunday springs')
        ->and($map->version)->toBe(2);
});

test('generated slugs retry a collision without overwriting the existing map', function () {
    $existing = Map::factory()->create(['slug' => 'abcd1234', 'title' => 'Existing map']);
    $slugs = ['AbCd1234', 'ZyXw9876'];
    Str::createRandomStringsUsing(function (int $length) use (&$slugs): string {
        return $length === 8 ? array_shift($slugs) : str_repeat('x', $length);
    });

    $this->actingAs(User::factory()->create())->postJson(route('maps.store'), $this->payload)
        ->assertCreated()
        ->assertJsonPath('slug', 'zyxw9876')->assertJsonMissingPath('token');

    expect($existing->refresh()->title)->toBe('Existing map');
    $this->assertDatabaseCount('maps', 2);
});

test('existing mixed case map slugs remain case sensitive in storage and public lookup', function () {
    $upper = Map::factory()->create(['slug' => 'AbCd1234', 'title' => 'Uppercase']);
    $lower = Map::factory()->create(['slug' => 'abcd1234', 'title' => 'Lowercase']);

    $this->get(route('maps.show', ['map' => $upper->slug]))
        ->assertOk()
        ->assertViewHas('sharedMap', fn (array $map): bool => $map['title'] === 'Uppercase');
    $this->get(route('maps.show', ['map' => $lower->slug]))
        ->assertOk()
        ->assertViewHas('sharedMap', fn (array $map): bool => $map['title'] === 'Lowercase');

    expect($upper->refresh()->views_count)->toBe(1)
        ->and($lower->refresh()->views_count)->toBe(1);
});

test('unknown or malformed map slugs return not found to JSON clients', function (string $slug) {
    $this->getJson('/maps/'.$slug)->assertNotFound();
})->with(['Missing1', 'abc', 'abc_defg', 'A12345678']);

test('only an explicit owner update replaces a map and leaves its identity and counter unchanged', function () {
    $owner = User::factory()->create();
    $map = Map::factory()->for($owner)->create(['views_count' => 42]);
    $id = $map->id;
    $slug = $map->slug;
    $this->payload['state']['center'] = [23.75, -12.25];

    $this->actingAs($owner)->patchJson(route('maps.update', ['map' => $id]), $this->payload + [
        'version' => 1,
        'user_id' => User::factory()->create()->id,
        'token' => 'Changed1',
        'views_count' => 0,
    ])
        ->assertOk()
        ->assertJsonPath('id', $id)->assertJsonPath('slug', $slug)->assertJsonMissingPath('token')
        ->assertJsonPath('version', 2)
        ->assertJsonPath('can_update', true);

    expect($map->refresh()->title)->toBe($this->payload['title'])
        ->and($map->state)->toEqual($this->payload['state'])
        ->and($map->user_id)->toBe($owner->id)
        ->and($map->id)->toBe($id)->and($map->slug)->toBe($slug)
        ->and($map->views_count)->toBe(42)
        ->and($map->version)->toBe(2);
});

test('a stale editor cannot overwrite newer map changes', function () {
    $owner = User::factory()->create();
    $map = Map::factory()->for($owner)->create();
    $url = route('maps.update', ['map' => $map->id]);
    $this->actingAs($owner)->patchJson($url, $this->payload + ['version' => 1])
        ->assertOk()->assertJsonPath('version', 2);
    $this->payload['title'] = 'Stale overwrite';

    $this->patchJson($url, $this->payload + ['version' => 1])->assertConflict();

    expect($map->refresh()->title)->toBe('Маршрут к источникам')
        ->and($map->version)->toBe(2);
});

test('an update requires a valid saved revision number', function (mixed $version) {
    $owner = User::factory()->create();
    $map = Map::factory()->for($owner)->create();

    $this->actingAs($owner)->patchJson(route('maps.update', ['map' => $map->id]), $this->payload + ['version' => $version])
        ->assertUnprocessable()->assertJsonValidationErrors('version');

    expect($map->refresh()->version)->toBe(1);
})->with([null, 0, -1, '1', true, 1.5, 4294967295]);

test('guests cannot update saved maps even when the map has no owner', function () {
    $map = Map::factory()->create();

    $this->patchJson(route('maps.update', ['map' => $map->id]), $this->payload + ['version' => 1])
        ->assertUnauthorized();

    expect(fn () => app(UpdateMapAction::class)(null, $map, $this->payload + ['version' => 1]))
        ->toThrow(AuthorizationException::class);
});

test('another signed in user cannot edit an owned or anonymous map', function (bool $anonymous) {
    $map = Map::factory()->create(['user_id' => $anonymous ? null : User::factory()->create()->id]);
    $original = $map->state;

    $this->actingAs(User::factory()->create())
        ->patchJson(route('maps.update', ['map' => $map->id]), $this->payload + ['version' => 1])
        ->assertForbidden();

    expect($map->refresh()->state)->toEqual($original);
})->with([true, false]);

test('a visitor can save a personal copy without modifying the original map', function () {
    $original = Map::factory()->create();
    $newOwner = User::factory()->create();

    $response = $this->actingAs($newOwner)
        ->postJson(route('maps.store'), [
            'title' => 'My copy',
            'state' => $original->state,
            'track_token' => null,
        ])
        ->assertCreated()
        ->assertJsonPath('can_update', true);

    expect($response->json('id'))->not->toBe($original->id)
        ->and($response->json('slug'))->not->toBe($original->slug)
        ->and($original->refresh()->version)->toBe(1)
        ->and($original->user_id)->toBeNull()
        ->and($original->title)->not->toBe('My copy');
    $this->assertDatabaseCount('maps', 2);
});

test('creating maps respects the action authorization gate', function () {
    Gate::before(fn (?User $user, string $ability): ?bool => $ability === 'create' ? false : null);

    expect(fn () => app(StoreMapAction::class)(null, $this->payload))->toThrow(AuthorizationException::class);
    $this->assertDatabaseEmpty('maps');
});

test('a saved map references an uploaded track without embedding its geometry or uploader', function () {
    $track = Track::factory()->create();
    $this->payload['track_token'] = $track->token;

    $response = $this->actingAs(User::factory()->create())->postJson(route('maps.store'), $this->payload)->assertCreated();
    $map = Map::query()->sole();

    $response->assertJsonPath('track.hash', $track->hash)
        ->assertJsonPath('track.token', $track->token)
        ->assertJsonPath('track.name', $track->name)
        ->assertJsonPath('track.url', route('tracks.show', ['token' => $track->token]))
        ->assertJsonMissingPath('track.track')->assertJsonMissingPath('track.user_id');
    expect($map->track->is($track))->toBeTrue();
});

test('an owner can replace or explicitly remove the saved track', function () {
    $owner = User::factory()->create();
    $first = Track::factory()->create();
    $second = Track::factory()->create();
    $map = Map::factory()->for($owner)->create(['track_id' => $first->id]);
    $url = route('maps.update', ['map' => $map->id]);
    $this->payload['track_token'] = $second->token;

    $this->actingAs($owner)->patchJson($url, $this->payload + ['version' => 1])
        ->assertOk()->assertJsonPath('track.hash', $second->hash);
    $this->payload['track_token'] = null;
    $this->patchJson($url, $this->payload + ['version' => 2])
        ->assertOk()->assertJsonPath('track', null);

    expect($map->refresh()->track_id)->toBeNull();
    $this->assertModelExists($first);
    $this->assertModelExists($second);
});

test('missing and invalid track tokens cannot be linked', function (mixed $token) {
    $this->payload['track_token'] = $token;

    $this->actingAs(User::factory()->create())->postJson(route('maps.store'), $this->payload)
        ->assertUnprocessable()->assertJsonValidationErrors('track_token');

    $this->assertDatabaseEmpty('maps');
})->with(['short', 'Missing123', str_repeat('a', 64), 'invalid_12', 42, [[]]]);

test('map page loads count each GET but not HEAD or fetching the track', function () {
    $track = Track::factory()->create();
    $map = Map::factory()->create(['track_id' => $track->id, 'updated_at' => now()->subDay()]);
    $updatedAt = $map->updated_at->toDateTimeString();
    $url = route('maps.show', ['map' => $map->slug]);

    $response = $this->get($url)->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex')
        ->assertViewHas('sharedMap', fn (array $map): bool => ! array_key_exists('views_count', $map) && ! array_key_exists('user_id', $map));
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
    $this->get($url)->assertOk();
    $this->head($url)->assertOk();
    $this->getJson(route('tracks.show', ['token' => $track->token]))->assertOk();

    expect($map->refresh()->views_count)->toBe(2)
        ->and($map->version)->toBe(1)
        ->and($map->updated_at->toDateTimeString())->toBe($updatedAt);
});

test('public map payload reflects whether the viewer owns the map without exposing the owner', function () {
    $owner = User::factory()->create();
    $map = Map::factory()->for($owner)->create();
    $url = route('maps.show', ['map' => $map->slug]);

    $this->get($url)->assertOk()->assertViewHas('sharedMap', fn (array $map): bool => $map['can_update'] === false);
    $this->actingAs($owner)->get($url)->assertOk()->assertViewHas('sharedMap', fn (array $map): bool => $map['can_update'] === true);
    $this->actingAs(User::factory()->create())->get($url)->assertOk()->assertViewHas('sharedMap', fn (array $map): bool => $map['can_update'] === false);
});

test('a public map survives deletion of its owner and becomes immutable', function () {
    $owner = User::factory()->create();
    $map = Map::factory()->for($owner)->create();
    $owner->delete();

    expect($map->refresh()->user_id)->toBeNull();
    $this->get(route('maps.show', ['map' => $map->slug]))
        ->assertOk()->assertViewHas('sharedMap', fn (array $map): bool => $map['can_update'] === false);
});

test('historical snapshots retain missing resources but open a usable map', function () {
    $this->payload['state']['page'] = ['spring' => 922337, 'user' => 922338, 'location' => 1];
    $created = $this->actingAs(User::factory()->create())->postJson(route('maps.store'), $this->payload)->assertCreated();

    $this->get($created->json('url'))->assertOk()->assertViewHas('sharedMap', function (array $map): bool {
        return $map['state']['page']['spring'] === null
            && $map['state']['page']['user'] === null
            && $map['state']['page']['location'] === null
            && $map['state']['center'] === $this->payload['state']['center'];
    });

    expect(Map::query()->sole()->state['page'])->toEqual($this->payload['state']['page']);
});

test('my maps requires authentication', function () {
    $this->get(route('maps.index'))->assertRedirect(route('login'));
});

test('my maps contains only the current users maps ordered by newest shared and paginated', function () {
    $owner = User::factory()->create();
    $old = Map::factory()->for($owner)->create(['created_at' => now()->subDays(2), 'updated_at' => now(), 'is_starred' => true]);
    Map::factory()->for($owner)->count(20)->create(['created_at' => now()->subDay(), 'updated_at' => now()->subDay()]);
    $foreign = Map::factory()->for(User::factory())->create();
    $guest = Map::factory()->create();

    $this->actingAs($owner)->get(route('maps.index'))
        ->assertOk()
        ->assertDontSee($foreign->slug)
        ->assertDontSee($guest->slug)
        ->assertViewHas('maps', fn ($maps): bool => $maps->total() === 21 && $maps->count() === 20 && ! $maps->contains($old));
    $this->get(route('maps.index', ['page' => 2]))
        ->assertOk()
        ->assertViewHas('maps', fn ($maps): bool => $maps->count() === 1 && $maps->first()->is($old));
});

test('localized map and collection routes preserve their language', function () {
    $map = Map::factory()->create();

    $this->get(route('ru.maps.show', ['map' => $map->slug]))
        ->assertOk()->assertSee('lang="ru"', false);
    $this->actingAs(User::factory()->create())->get(route('ru.maps.index'))
        ->assertOk()->assertSee('lang="ru"', false);
});

test('invalid map state values and unrecognized keys are rejected', function (string $path, mixed $value) {
    data_set($this->payload, $path, $value);

    $errorPath = match ($path) {
        'state.admin', 'state.extra' => 'state',
        'state.filters.admin', 'state.filters.confirmed' => 'state.filters',
        'state.overlays.custom' => 'state.overlays',
        'state.page.admin' => 'state.page',
        default => $path,
    };

    $this->actingAs(User::factory()->create())->postJson(route('maps.store'), $this->payload)
        ->assertUnprocessable()->assertJsonValidationErrors($errorPath);

    $this->assertDatabaseEmpty('maps');
})->with([
    'nonstring title' => ['title', ['invalid']],
    'long title' => ['title', str_repeat('я', 161)],
    'missing state shape' => ['state', []],
    'future schema' => ['state.version', 2],
    'string schema' => ['state.version', '1'],
    'extra state key' => ['state.admin', true],
    'too much state' => ['state.extra', str_repeat('x', 16385)],
    'too many coordinates' => ['state.center', [0, 0, 0]],
    'center object' => ['state.center', ['longitude' => 0, 'latitude' => 0]],
    'longitude range' => ['state.center.0', 181],
    'latitude range' => ['state.center.1', -91],
    'numeric string coordinate' => ['state.center.0', '37.7'],
    'invalid zoom' => ['state.zoom', -1],
    'huge zoom' => ['state.zoom', 29],
    'numeric string zoom' => ['state.zoom', '12'],
    'unrecognized tile source' => ['state.sourceName', 'https://evil.example/tiles'],
    'unrecognized filter' => ['state.filters.admin', true],
    'removed confirmed filter' => ['state.filters.confirmed', true],
    'string reports filter' => ['state.filters.with_reports', 'true'],
    'string filter' => ['state.filters.spring', 'true'],
    'integer filter' => ['state.filters.along', 1],
    'unrecognized overlay' => ['state.overlays.custom', true],
    'integer overlay' => ['state.overlays.osmTraces', 1],
    'unrecognized page option' => ['state.page.admin', 1],
    'nonpositive spring' => ['state.page.spring', 0],
    'negative user' => ['state.page.user', -1],
    'string user' => ['state.page.user', '1'],
    'invalid location' => ['state.page.location', 2],
    'string fullscreen' => ['state.fullscreen', 'true'],
    'integer minimized' => ['state.minimized', 0],
]);

test('snapshot keys must be explicitly provided even when false or null', function (string $path) {
    data_forget($this->payload, $path);

    $this->actingAs(User::factory()->create())->postJson(route('maps.store'), $this->payload)
        ->assertUnprocessable()->assertJsonValidationErrors($path);

    $this->assertDatabaseEmpty('maps');
})->with(['track_token', 'state.page.spring', 'state.page.user', 'state.page.location', 'state.filters.other', 'state.filters.with_reports', 'state.overlays.osmTraces', 'state.fullscreen']);

test('map mutations are rate limited independently of reads', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.55']);
    $this->actingAs(User::factory()->create());

    for ($request = 0; $request < 21; $request++) {
        $this->getJson('/maps/missing-map')->assertNotFound();
    }

    for ($request = 0; $request < 20; $request++) {
        $this->postJson(route('maps.store'), $this->payload)->assertCreated();
    }

    $this->postJson(route('maps.store'), $this->payload)->assertTooManyRequests();
    $this->assertDatabaseCount('maps', 20);
});

test('full view updates and renames reject blank names using the current page language', function (string $locale, ?string $title) {
    $owner = User::factory()->create(['locale' => $locale === 'ru' ? 'en' : 'ru']);
    $map = Map::factory()->for($owner)->create(['title' => 'Sunday springs']);
    $original = $map->refresh()->getAttributes();
    $this->actingAs($owner)->withHeader('X-Rodnik-Locale', $locale);
    $expected = __('ui.maps.title_required', locale: $locale);
    $payload = array_replace($this->payload, ['title' => $title, 'version' => 1]);

    $this->patchJson(route('maps.update', $map), $payload)->assertUnprocessable()->assertJsonPath('errors.title.0', $expected);
    $this->patchJson(route('maps.rename', $map), ['title' => $title, 'version' => 1])->assertUnprocessable()->assertJsonPath('errors.title.0', $expected);
    expect($map->refresh()->getAttributes())->toEqual($original);
})->with(['en', 'ru'])->with(['null' => null, 'empty' => '', 'whitespace' => " \t\u{2003} "]);
