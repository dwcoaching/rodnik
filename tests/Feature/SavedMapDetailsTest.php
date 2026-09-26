<?php

declare(strict_types=1);

use App\Models\Map;
use App\Models\Track;
use App\Models\User;
use App\Support\MapPayload;
use Dom\HTMLDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Js;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('owners create a named map with the supplied view and ignore the removed description field', function () {
    $owner = User::factory()->create();
    $state = app(MapPayload::class)->defaultState();
    $state['center'] = [29.6, 36.5];
    $state['zoom'] = 9.75;
    $response = $this->actingAs($owner)->postJson(route('maps.store'), [
        'title' => 'Lycian Way', 'description' => 'Water for our walk.', 'slug' => 'lycian-way',
        'state' => $state, 'track_token' => null,
    ])->assertCreated()->assertJsonPath('slug', 'lycian-way')->assertJsonPath('can_update', true)
        ->assertJsonPath('url', url('/maps/lycian-way').'/')->assertJsonMissingPath('description');

    $map = $owner->maps()->sole();
    expect($map->state)->toEqual($state)
        ->and($map->track_id)->toBeNull()
        ->and($map->slug)->toBe('lycian-way')
        ->and($map->getAttributes())->not->toHaveKey('description')
        ->and(Schema::hasColumn('maps', 'description'))->toBeFalse();
    $this->get($response->json('url'))->assertOk()->assertHeader('X-Robots-Tag', 'noindex')
        ->assertViewHas('sharedMap', fn (array $data): bool => $data['title'] === 'Lycian Way');
});

test('an omitted URL name generates a lowercase public slug without a token', function () {
    $this->actingAs(User::factory()->create())->postJson(route('maps.store'), ['title' => 'My route', 'state' => app(MapPayload::class)->defaultState(), 'track_token' => null])
        ->assertCreated()->assertJsonPath('slug', fn (string $slug): bool => preg_match('/^[a-z0-9]{8}$/', $slug) === 1);
    $map = Map::query()->sole();
    expect($map->getAttributes())->not->toHaveKey('token')
        ->and(Schema::hasColumn('maps', 'token'))->toBeFalse();
    $this->get('/maps/'.$map->slug)->assertOk();
});

test('a custom link cannot create a saved map without a name', function () {
    $owner = User::factory()->create();
    $state = app(MapPayload::class)->defaultState();
    $state['zoom'] = 8.25;

    $this->actingAs($owner)->postJson(route('maps.store'), [
        'slug' => 'lycian-way', 'state' => $state, 'track_token' => null,
    ])->assertUnprocessable()->assertJsonValidationErrors('title');

    $this->assertDatabaseEmpty('maps');
});

test('empty map names fail with localized guidance when creating a saved map', function (?string $title, string $locale) {
    $owner = User::factory()->create(['locale' => $locale === 'ru' ? 'en' : 'ru']);
    $this->actingAs($owner)->withHeader('X-Rodnik-Locale', $locale);
    $this->postJson(route('maps.store'), [
        'title' => $title, 'state' => app(MapPayload::class)->defaultState(), 'track_token' => null,
    ])->assertUnprocessable()->assertJsonPath('errors.title.0', __('ui.maps.title_required', locale: $locale));

    $this->assertDatabaseEmpty('maps');
})->with(['null' => null, 'empty' => '', 'whitespace' => " \t\u{2003} "])->with(['en', 'ru']);

test('clearing a map name fails without changing its link view or revision', function (?string $title, bool $replaceView, string $locale) {
    $owner = User::factory()->create(['locale' => $locale === 'ru' ? 'en' : 'ru']);
    $map = Map::factory()->for($owner)->create(['title' => 'Lycian Way', 'slug' => 'lycian-way']);
    $original = $map->refresh()->getAttributes();
    $payload = ['title' => $title, 'version' => 1];
    if ($replaceView) {
        $state = $map->state;
        $state['zoom'] = 8.25;
        $payload += ['state' => $state, 'track_token' => null];
    }

    $this->actingAs($owner)->withHeader('X-Rodnik-Locale', $locale)
        ->patchJson(route('maps.details', $map), $payload)
        ->assertUnprocessable()->assertJsonPath('errors.title.0', __('ui.maps.title_required', locale: $locale));

    expect($map->refresh()->getAttributes())->toEqual($original);
})->with(['null' => null, 'empty' => '', 'whitespace' => " \t\u{2003} "])
    ->with(['name only' => false, 'name and view' => true])->with(['en', 'ru']);

test('the name zero is preserved when creating and editing a map', function () {
    $owner = User::factory()->create();
    $this->actingAs($owner)->postJson(route('maps.store'), [
        'title' => '0', 'state' => app(MapPayload::class)->defaultState(), 'track_token' => null,
    ])->assertCreated()->assertJsonPath('title', '0');
    $map = $owner->maps()->sole();

    $this->patchJson(route('maps.details', $map), ['title' => '  0  ', 'version' => 1])
        ->assertOk()->assertJsonPath('title', '0');
    expect($map->refresh()->title)->toBe('0');
});

test('owners save URL and view changes without changing existing metadata', function (bool $replaceView) {
    $owner = User::factory()->create();
    $map = Map::factory()->for($owner)->create([
        'title' => 'Lycian Way', 'slug' => 'old-route',
    ]);
    $state = $map->state;
    $payload = ['slug' => 'lycian-way', 'version' => 1];
    if ($replaceView) {
        $state['zoom'] = 8.25;
        $payload += ['state' => $state, 'track_token' => null];
    }

    $this->actingAs($owner)->patchJson(route('maps.details', $map), $payload)
        ->assertOk()->assertJsonPath('title', 'Lycian Way')->assertJsonMissingPath('description')
        ->assertJsonPath('url', url('/maps/lycian-way').'/')->assertJsonPath('version', 2);

    expect($map->refresh()->title)->toBe('Lycian Way')
        ->and($map->state)->toEqual($state)->and($map->slug)->toBe('lycian-way');
})->with(['URL only' => false, 'URL and view' => true]);

test('legacy description edits and clears cannot recreate the removed field', function () {
    $owner = User::factory()->create();
    $map = Map::factory()->for($owner)->create(['title' => 'Lycian Way']);

    $this->actingAs($owner)->patchJson(route('maps.details', $map), [
        'description' => '  Private water stops  ', 'version' => 1,
    ])->assertOk()->assertJsonMissingPath('description')->assertJsonPath('title', 'Lycian Way');

    expect($map->refresh()->getAttributes())->not->toHaveKey('description')->and($map->title)->toBe('Lycian Way');
    $this->get(route('maps.index'))->assertOk()->assertDontSee('Private water stops');
    $this->getJson(route('maps.options'))->assertOk()->assertJsonMissingPath('data.0.description');

    $this->patchJson(route('maps.details', $map), ['description' => '', 'version' => 2])
        ->assertOk()->assertJsonMissingPath('description');
    expect($map->refresh()->getAttributes())->not->toHaveKey('description');
});

test('map management still restricts names and obsolete-field writes to the owner', function () {
    $owner = User::factory()->create();
    $map = Map::factory()->for($owner)->create(['title' => 'Private trip planning 4fc2']);
    $visitor = User::factory()->create();

    $this->actingAs($visitor)->get(route('maps.index'))->assertOk()->assertDontSee($map->title);
    $this->getJson(route('maps.options'))->assertOk()->assertJsonCount(0, 'data');
    $this->get(route('maps.edit', ['map' => $map->id]))->assertForbidden()->assertDontSee($map->title);
    $this->patchJson(route('maps.details', $map), ['description' => 'Changed', 'version' => 1])->assertForbidden();

    expect($map->refresh()->title)->toBe('Private trip planning 4fc2')
        ->and($map->ownerData($owner))->not->toHaveKey('description')
        ->and($map->ownerData($visitor))->not->toHaveKey('description')
        ->and($map->ownerData(null))->not->toHaveKey('description');
});

test('obsolete description values are ignored instead of validated or saved', function (mixed $description) {
    $owner = User::factory()->create();
    $state = app(MapPayload::class)->defaultState();
    $this->actingAs($owner)->postJson(route('maps.store'), [
        'title' => 'Lycian Way', 'description' => $description, 'state' => $state, 'track_token' => null,
    ])->assertCreated()->assertJsonMissingPath('description');
    $map = $owner->maps()->sole();

    $this->patchJson(route('maps.details', $map), [
        'title' => 'Water stops', 'description' => $description, 'version' => 1,
    ])->assertOk()->assertJsonPath('title', 'Water stops')->assertJsonMissingPath('description');

    expect($map->refresh()->getAttributes())->not->toHaveKey('description')
        ->and($map->state)->toEqual($state);
})->with([
    'over the former limit' => [str_repeat('x', 2001)],
    'structured value' => [['unexpected' => 'value']],
    'boolean value' => [true],
]);

test('changing details preserves the saved view and replaces the public slug', function () {
    $owner = User::factory()->create();
    $map = Map::factory()->for($owner)->create(['slug' => 'old-route']);
    $original = $map->state;
    $this->actingAs($owner)->patchJson(route('maps.details', $map), [
        'title' => 'Lycian Way', 'description' => 'After', 'slug' => 'lycian-way', 'version' => 1,
        'user_id' => 999,
    ])->assertOk()->assertJsonPath('version', 2)->assertJsonPath('url', url('/maps/lycian-way').'/');

    expect($map->refresh()->state)->toEqual($original)->and($map->user_id)->toBe($owner->id);
    $this->getJson('/maps/old-route')->assertNotFound();
    $this->getJson(route('maps.check-slug', ['slug' => 'old-route']))->assertOk();
    $this->postJson(route('maps.store'), ['title' => 'Reused name', 'slug' => 'old-route', 'state' => $original, 'track_token' => null])->assertCreated();
    $this->get('/maps/lycian-way')->assertOk()->assertViewHas('sharedMap', fn (array $data): bool => $data['id'] === $map->id && $data['title'] === 'Lycian Way');
});

test('numeric map mutation URLs keep working after its public link changes', function () {
    $owner = User::factory()->create();
    $map = Map::factory()->for($owner)->create(['slug' => 'old-route', 'views_count' => 42]);
    $detailsUrl = route('maps.details', $map);
    $renameUrl = route('maps.rename', $map);
    $updateUrl = route('maps.update', $map);

    expect($detailsUrl)->toBe(url('/maps/'.$map->id.'/details'))
        ->and($renameUrl)->toBe(url('/maps/'.$map->id.'/title'))
        ->and($updateUrl)->toBe(url('/maps/'.$map->id));

    $this->actingAs($owner)->patchJson($detailsUrl, ['slug' => 'new-route', 'version' => 1])
        ->assertOk()->assertJsonPath('id', $map->id)->assertJsonMissingPath('token');
    $this->getJson('/maps/old-route')->assertNotFound();
    $this->getJson('/maps/new-route')->assertOk()->assertJsonPath('id', $map->id);
    $this->patchJson($renameUrl, ['title' => 'New name', 'version' => 2])->assertOk();
    $state = $map->state;
    $state['zoom'] = 8.5;
    $this->patchJson($updateUrl, ['state' => $state, 'track_token' => null, 'version' => 3])
        ->assertOk()->assertJsonPath('slug', 'new-route')->assertJsonPath('version', 4);
    $this->getJson(route('maps.check-slug', ['slug' => 'new-route', 'map' => $map->id]))->assertOk();

    expect($map->refresh()->views_count)->toBe(42)
        ->and($map->title)->toBe('New name')->and($map->state)->toEqual($state);
});

test('owners atomically update map details and the view from a pasted link', function () {
    $owner = User::factory()->create();
    $track = Track::factory()->create();
    $map = Map::factory()->for($owner)->create(['slug' => 'old-route', 'views_count' => 12]);
    $state = $map->state;
    $state['center'] = [29.6, 36.5];
    $state['zoom'] = 9.75;

    $this->actingAs($owner)->patchJson(route('maps.details', $map), [
        'title' => '  Lycian Way  ', 'description' => 'Water stops', 'slug' => 'lycian-way', 'version' => 1,
        'state' => $state, 'track_token' => $track->token,
    ])->assertOk()->assertJsonPath('version', 2)->assertJsonPath('title', 'Lycian Way')
        ->assertJsonPath('state.center', $state['center'])->assertJsonPath('track.hash', $track->hash)
        ->assertJsonPath('url', url('/maps/lycian-way').'/');

    expect($map->refresh()->state)->toEqual($state)->and($map->track_id)->toBe($track->id)
        ->and($map->views_count)->toBe(12)->and($map->slug)->toBe('lycian-way');
    $this->patchJson(route('maps.details', $map), [
        'title' => $map->title, 'version' => 2, 'state' => $state, 'track_token' => null,
    ])->assertOk()->assertJsonPath('version', 3)->assertJsonPath('track', null);
});

test('invalid replacement views leave map details and slug unchanged', function (array $replacement, string $error) {
    $owner = User::factory()->create();
    $map = Map::factory()->for($owner)->create();
    $original = $map->refresh()->getAttributes();

    $this->actingAs($owner)->patchJson(route('maps.details', $map), $replacement + [
        'title' => 'Changed', 'slug' => 'must-not-reserve', 'description' => 'Changed', 'version' => 1,
    ])->assertUnprocessable()->assertJsonValidationErrors($error);

    expect($map->refresh()->getAttributes())->toEqual($original)
        ->and(Map::query()->where('slug', 'must-not-reserve')->exists())->toBeFalse();
})->with([
    'track without state' => [['track_token' => null], 'state'],
    'state without track' => [fn (): array => ['state' => app(MapPayload::class)->defaultState()], 'track_token'],
    'malformed state' => [['state' => ['invalid' => true], 'track_token' => null], 'state'],
    'missing track' => [fn (): array => ['state' => app(MapPayload::class)->defaultState(), 'track_token' => str_repeat('a', 64)], 'track_token'],
]);

test('the library includes the saved view and track for full URL copies', function () {
    $owner = User::factory()->create();
    $track = Track::factory()->create();
    $map = Map::factory()->for($owner)->create(['track_id' => $track->id]);

    $response = $this->actingAs($owner)->get(route('maps.index'))->assertOk();
    $listed = $response->viewData('maps')->sole();

    expect($listed->state)->toEqual($map->state)->and($listed->track_id)->toBe($track->id)
        ->and($listed->publicData($owner)['can_update'])->toBeTrue()
        ->and($listed->publicData($owner)['url'])->toBe(url('/maps/'.$map->slug).'/');
});

test('clearing a custom URL keeps the current public link', function () {
    $owner = User::factory()->create();
    $map = Map::factory()->for($owner)->create(['slug' => 'lycian-way']);
    $this->actingAs($owner)->patchJson(route('maps.details', $map), [
        'title' => $map->title, 'description' => null, 'slug' => null, 'version' => 1,
    ])->assertOk()->assertJsonPath('slug', 'lycian-way')->assertJsonMissingPath('description');
    $this->getJson('/maps/lycian-way')->assertOk()->assertJsonPath('id', $map->id);
    expect($map->refresh()->slug)->toBe('lycian-way');
});

test('updating the current view preserves all metadata and the public URL', function () {
    $owner = User::factory()->create();
    $map = Map::factory()->for($owner)->create(['title' => 'Lycian Way', 'slug' => 'lycian-way']);
    $state = $map->state;
    $state['zoom'] = 8.25;
    $this->actingAs($owner)->patchJson(route('maps.update', $map), [
        'state' => $state, 'track_token' => null, 'version' => 1,
    ])->assertOk()->assertJsonPath('title', 'Lycian Way')->assertJsonMissingPath('description')
        ->assertJsonPath('url', url('/maps/lycian-way').'/')->assertJsonPath('state.zoom', 8.25)->assertJsonPath('version', 2);
});

test('saved map details and picker are restricted to the owner', function () {
    $owner = User::factory()->create();
    $map = Map::factory()->for($owner)->create();
    $payload = ['title' => 'Changed', 'slug' => 'changed', 'version' => 1];
    $this->patchJson(route('maps.details', $map), $payload)->assertUnauthorized();
    $this->getJson(route('maps.options'))->assertUnauthorized();
    $this->actingAs(User::factory()->create())->patchJson(route('maps.details', $map), $payload)->assertForbidden();
    $this->getJson(route('maps.options'))->assertOk()->assertJsonCount(0, 'data');
    expect($map->refresh()->version)->toBe(1);
});

test('guests can open existing public maps but cannot create saved maps', function () {
    $state = app(MapPayload::class)->defaultState();
    $this->postJson(route('maps.store'), ['title' => 'Named', 'slug' => 'lycian-way', 'state' => $state, 'track_token' => null])->assertUnauthorized();
    $this->postJson(route('maps.store'), ['title' => 'Named'])->assertUnauthorized();
    $map = Map::factory()->create();
    $this->getJson(route('maps.show', $map))->assertOk()->assertJsonPath('can_update', false);
});

test('current URL names cannot be claimed by another map', function () {
    $owner = User::factory()->create();
    $first = Map::factory()->for($owner)->create(['slug' => 'reserved']);
    $second = Map::factory()->for($owner)->create();
    $originalSlug = $second->slug;
    foreach (['reserved'] as $slug) {
        $this->actingAs($owner)->patchJson(route('maps.details', $second), [
            'title' => 'Other', 'slug' => $slug, 'version' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors('slug');
        $this->postJson(route('maps.store'), ['title' => 'Other', 'slug' => $slug, 'state' => app(MapPayload::class)->defaultState(), 'track_token' => null])->assertUnprocessable()->assertJsonValidationErrors('slug');
    }
    expect($second->refresh()->version)->toBe(1)->and($second->slug)->toBe($originalSlug);
});

test('invalid map details are rejected without changing the saved record', function (string $field, mixed $value) {
    $owner = User::factory()->create();
    $map = Map::factory()->for($owner)->create();
    $this->actingAs($owner)->patchJson(route('maps.details', $map), array_replace([
        'title' => 'Valid', 'slug' => 'valid-name', 'version' => 1,
    ], [$field => $value]))->assertUnprocessable()->assertJsonValidationErrors($field);
    expect($map->refresh()->version)->toBe(1);
})->with([
    ['slug', 'ab'], ['slug', 'a--b'], ['slug', '../admin'], ['slug', 'маршрут'], ['slug', str_repeat('a', 81)],
    ['title', false], ['title', 123],
    ['title', ['invalid']], ['title', str_repeat('x', 161)], ['version', '1'],
]);

test('stale detail edits cannot overwrite a newer view or change the slug', function () {
    $owner = User::factory()->create();
    $map = Map::factory()->for($owner)->create(['version' => 2]);
    $original = $map->refresh()->getAttributes();
    $state = $map->state;
    $state['zoom'] = 1.5;
    $this->actingAs($owner)->patchJson(route('maps.details', $map), [
        'title' => 'Stale', 'slug' => 'stale-alias', 'version' => 1,
        'state' => $state, 'track_token' => null,
    ])->assertConflict();
    expect(Map::query()->where('slug', 'stale-alias')->exists())->toBeFalse()->and($map->refresh()->getAttributes())->toEqual($original);
});

test('the searchable map picker paginates only the signed in users maps', function () {
    $owner = User::factory()->create();
    Map::factory()->for($owner)->count(21)->create(['title' => 'Lycian Way']);
    Map::factory()->for($owner)->create(['title' => 'Elsewhere']);
    Map::factory()->for(User::factory()->create())->create(['title' => 'Lycian Way']);
    $response = $this->actingAs($owner)->getJson(route('maps.options', ['q' => 'Lycian']))
        ->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertJsonCount(20, 'data');
    foreach ($response->json('data') as $map) {
        expect($map['can_update'])->toBeTrue()->and($map)->not->toHaveKey('user_id');
    }
    $this->getJson($response->json('next_page_url'))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('next_page_url', null);
});

test('my maps uses compact rows and edits names and links in one dialog', function () {
    $owner = User::factory()->create();
    Map::factory()->for($owner)->create(['slug' => 'lycian-way']);
    $response = $this->actingAs($owner)->get(route('maps.index'))->assertOk()
        ->assertSee('<title>My maps — Rodnik.today</title>', false)
        ->assertDontSeeText(trans('ui.maps.library_description'))
        ->assertSee('id="map-search"', false)->assertSee('@input="scheduleSearch()"', false)
        ->assertSee('id="map-favorites"', false)->assertSee('favorites = true; filterChanged()', false)
        ->assertSee('Copy link')->assertDontSee('Copy full URL')
        ->assertSee('id="map-editor-dialog"', false)->assertSee('id="map-delete-dialog"', false)
        ->assertDontSee(trans('ui.maps.explore_map'))
        ->assertDontSee('item.created_at', false)->assertDontSee('item.track_name', false)
        ->assertDontSee('editingLink', false)->assertDontSee('edit(true)', false);

    $document = HTMLDocument::createFromString($response->getContent(), LIBXML_NOERROR);
    $main = $document->querySelector('main[x-data]');
    $row = HTMLDocument::createFromString($main->querySelector('template[x-for]')->innerHTML, LIBXML_NOERROR);
    $editor = HTMLDocument::createFromString($main->querySelector('template[x-if="editingRecord"]')->innerHTML, LIBXML_NOERROR);
    $modal = HTMLDocument::createFromString($editor->querySelector('template[x-teleport]')->innerHTML, LIBXML_NOERROR);
    $copy = $row->querySelector('button[data-map-link]');
    $link = $copy->querySelector('span[x-text]');
    $copyLabel = $copy->querySelector('span[x-show="copied"]');
    $title = $row->querySelector('[x-text="title"]');
    $form = $modal->querySelector('form');
    $name = $modal->querySelector('input[x-ref="title"]');
    $slug = $modal->querySelector('input[x-ref="slug"]');
    $help = $main->querySelector('header #map-library-help');
    $share = $help->querySelector('span');
    $shareDot = $share->querySelector('.bg-blue-600');
    expect($main->getAttribute('x-data'))->toStartWith('mapLibrary(')
        ->and($main->querySelectorAll('[role="search"] button')->length)->toBe(2)
        ->and($main->querySelector('nav a[aria-current="page"]')->getAttribute('href'))->toBe(route('maps.index'))
        ->and($main->querySelector('nav a:last-child')->getAttribute('href'))->toBe(route('tracks.index'))
        ->and($main->querySelectorAll('header p')->length)->toBe(1)
        ->and(Str::squish($help->textContent))->toBe(trans('ui.maps.library_edit_help', ['share' => trans('ui.maps.share')]))
        ->and($help->querySelectorAll('button, a, [role="button"], [tabindex], [x-show], [x-cloak]')->length)->toBe(0)
        ->and($share->classList->contains('inline-flex'))->toBeTrue()
        ->and($share->hasAttribute('@click'))->toBeFalse()
        ->and($share->querySelector('svg')->getAttribute('viewBox'))->toBe('0 0 24 24')
        ->and($share->querySelector('svg path')->getAttribute('d'))->toBe('M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71m2.25 5.82a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71')
        ->and($shareDot->classList->contains('size-2'))->toBeTrue()
        ->and($shareDot->classList->contains('rounded-full'))->toBeTrue()
        ->and($shareDot->getAttribute('aria-hidden'))->toBe('true')
        ->and($row->querySelector('form'))->toBeNull()
        ->and($row->querySelectorAll('a')->length)->toBe(1)
        ->and($title->localName)->toBe('a')
        ->and($title->getAttribute(':href'))->toBe('url')
        ->and($title->classList->contains('text-base'))->toBeTrue()
        ->and($title->classList->contains('font-semibold'))->toBeTrue()
        ->and($title->classList->contains('font-bold'))->toBeFalse()
        ->and($title->classList->contains('block'))->toBeFalse()
        ->and($title->classList->contains('inline-block'))->toBeFalse()
        ->and($title->classList->contains('flex'))->toBeFalse()
        ->and($title->parentElement->hasAttribute('data-map-title'))->toBeTrue()
        ->and(preg_match('/(?:^|\\s)(?:[a-z]+:)?p[xytrlbse]?-/', $title->getAttribute('class')))->toBe(0)
        ->and($title->parentElement->localName)->toBe('div')
        ->and($title->parentElement->hasAttribute('@click'))->toBeFalse()
        ->and($link->localName)->toBe('span')
        ->and($link->getAttribute('x-text'))->toBe('url')
        ->and($link->hasAttribute(':href'))->toBeFalse()
        ->and($link->parentNode->isSameNode($copy))->toBeTrue()
        ->and($copy->getAttribute('type'))->toBe('button')
        ->and($copy->getAttribute('@click'))->toBe('copy()')
        ->and($copy->classList->contains('border'))->toBeFalse()
        ->and($copy->getAttribute(':class'))->toContain('bg-transparent text-zinc-500', 'hover:bg-blue-50', 'hover:text-blue-700')
        ->and($copy->getAttribute(':class'))->not->toContain('border-')
        ->and($copy->classList->contains('h-6'))->toBeTrue()
        ->and($copy->classList->contains('-ml-2'))->toBeTrue()
        ->and($copy->classList->contains('px-2'))->toBeTrue()
        ->and($copy->classList->contains('font-normal'))->toBeTrue()
        ->and($copy->classList->contains('focus-visible:bg-blue-50'))->toBeTrue()
        ->and($copy->classList->contains('focus-visible:text-blue-700'))->toBeTrue()
        ->and($copy->querySelectorAll('a')->length)->toBe(0)
        ->and($copy->querySelector('svg[x-show="!copied"]'))->not->toBeNull()
        ->and($copy->getAttribute(':disabled'))->toBe('!canCopyLink()')
        ->and($copy->getAttribute(':aria-label'))->toBe('copied ? '.Js::from(trans('ui.maps.copied')).' : '.Js::from(trans('ui.maps.copy_link')))
        ->and($copyLabel->getAttribute('x-show'))->toBe('copied')
        ->and($copyLabel->hasAttribute('x-cloak'))->toBeTrue()
        ->and(mb_trim($copyLabel->textContent))->toBe(trans('ui.maps.copied'))
        ->and($row->querySelector('[data-map-actions]')->classList->contains('absolute'))->toBeTrue()
        ->and($row->querySelector('[data-map-actions]')->classList->contains('sm:static'))->toBeTrue()
        ->and($link->classList->contains('text-sm'))->toBeTrue()
        ->and($link->classList->contains('leading-5'))->toBeTrue()
        ->and($row->querySelectorAll('div[x-show="menuOpen"] button')->length)->toBe(2)
        ->and($modal->querySelector('[role="dialog"]')->getAttribute('aria-modal'))->toBe('true')
        ->and($form->hasAttribute('novalidate'))->toBeTrue()
        ->and($form->getAttribute('@submit.prevent'))->toBe('saveInline(true)')
        ->and($form->getAttribute('@keydown.enter'))->toContain('isComposing', 'preventDefault()')
        ->and($name->getAttribute('maxlength'))->toBe('160')
        ->and($name->getAttribute('x-model'))->toBe('draftTitle')
        ->and($name->hasAttribute('required'))->toBeTrue()
        ->and($name->getAttribute('@input'))->toContain('fieldErrors.title')
        ->and($name->getAttribute('placeholder'))->toBe(trans('ui.maps.title_placeholder'))
        ->and($slug->getAttribute('x-model'))->toBe('draftSlug')
        ->and($slug->getAttribute('@input'))->toBe('slugInput()')
        ->and($slug->getAttribute('maxlength'))->toBe('80')
        ->and($slug->hasAttribute('required'))->toBeTrue()
        ->and($slug->getAttribute('placeholder'))->toBe('lycian-way')
        ->and($modal->querySelector('[x-show="slugStatus === \'valid\'"]')->classList->contains('text-[#198754]'))->toBeTrue()
        ->and($modal->querySelector('svg[x-show="slugStatus === \'invalid\' || slugStatus === \'failed\'"]')->classList->contains('text-[#dc3545]'))->toBeTrue()
        ->and($slug->parentElement->getAttribute(':class'))->toContain('border-[#198754] ring-1 ring-[#198754]', 'focus-within:border-[#198754]', 'focus-within:ring-[#198754]', 'border-[#dc3545] ring-1 ring-[#dc3545]', 'focus-within:border-[#dc3545]', 'focus-within:ring-[#dc3545]', 'focus-within:border-blue-600', 'focus-within:ring-blue-600')
        ->and($slug->parentElement->getAttribute(':class'))->not->toContain('/25')
        ->and($slug->parentElement->classList->contains('focus-within:ring-1'))->toBeTrue()
        ->and($modal->querySelector('p[x-text="slugNotice"]')->getAttribute('x-show'))->toBe("(slugStatus === 'invalid' || slugStatus === 'failed') && slugNotice && !fieldErrors.slug")
        ->and($modal->querySelector('p[x-text="slugNotice"]')->classList->contains('text-[#dc3545]'))->toBeTrue()
        ->and($modal->querySelector('p[x-show="fieldErrors.slug"]')->classList->contains('text-[#dc3545]'))->toBeTrue()
        ->and($modal->querySelector('p[x-show="fieldErrors.title"]')->classList->contains('text-[#dc3545]'))->toBeTrue()
        ->and($form->querySelector('button[type="submit"]')->getAttribute(':disabled'))->toBe("busy || slugStatus !== 'valid'")
        ->and(mb_trim($form->querySelector('button[type="submit"]')->textContent))->toBe(trans('ui.common.save_changes'));

    foreach (['title' => 'title_example', 'slug' => 'link_example'] as $field => $translation) {
        $input = $form->querySelector('input[x-ref="'.$field.'"]');
        $exampleId = $input->getAttribute('id').'-example';
        $example = $form->querySelector('#'.$exampleId);
        expect($input->getAttribute('aria-describedby'))->toContain($exampleId)
            ->and($example->textContent)->toBe(trans('ui.maps.'.$translation))
            ->and($example->classList->contains('text-zinc-500'))->toBeTrue()
            ->and($example->previousElementSibling->getAttribute('for'))->toBe($input->getAttribute('id'));
    }
});

test('legacy edit URLs select an owned map for the shared edit dialog', function (string $prefix) {
    $owner = User::factory()->create();
    $map = Map::factory()->for($owner)->create(['title' => 'Target route', 'is_starred' => false]);
    Map::factory()->for($owner)->count(21)->create(['title' => 'Other route', 'is_starred' => true]);

    $response = $this->actingAs($owner)->get($prefix.'/user/maps/'.$map->id.'/edit?q=Other&favorites=1')
        ->assertOk()->assertViewHas('editingMap', fn (Map $editing): bool => $editing->is($map))
        ->assertSee('Target route')->assertSee('editingRecord', false)
        ->assertSee('placeholder="lycian-way"', false)
        ->assertSee('id="map-editor-dialog"', false)
        ->assertSee('id="map-editor-title"', false)
        ->assertSee('id="map-delete-dialog"', false);

    $locale = $prefix === '/ru' ? 'ru' : 'en';
    $document = HTMLDocument::createFromString($response->getContent(), LIBXML_NOERROR);
    expect(Str::squish($document->querySelector('#map-library-help')->textContent))
        ->toBe(trans('ui.maps.library_edit_help', ['share' => trans('ui.maps.share', locale: $locale)], $locale));
})->with(['English' => '', 'Russian' => '/ru']);

test('my maps searches the complete collection and keeps the query on later pages', function () {
    $owner = User::factory()->create();
    Map::factory()->for($owner)->count(21)->create(['title' => 'Lycian Way']);
    Map::factory()->for($owner)->create(['title' => 'Elsewhere']);
    $foreign = Map::factory()->for(User::factory()->create())->create(['title' => 'Lycian Way private']);

    $response = $this->actingAs($owner)->get(route('maps.index', ['q' => '  Lycian  ']))
        ->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertViewHas('search', 'Lycian')
        ->assertDontSee($foreign->slug)->assertDontSee('Elsewhere');
    $maps = $response->viewData('maps');
    expect($maps->total())->toBe(21)->and($maps)->toHaveCount(20);
    $this->get($maps->nextPageUrl())->assertOk()->assertViewHas('search', 'Lycian')
        ->assertViewHas('maps', fn ($maps): bool => $maps->count() === 1 && $maps->total() === 21);
});

test('my maps searches names and link names while treating wildcard characters literally', function () {
    $owner = User::factory()->create();
    $named = Map::factory()->for($owner)->create(['title' => 'Water with 100% certainty']);
    $slug = Map::factory()->for($owner)->create(['slug' => 'lycian-trail']);
    Map::factory()->for($owner)->create(['title' => 'An unrelated map']);
    $this->actingAs($owner)->get(route('maps.index', ['q' => '100%']))->assertOk()
        ->assertViewHas('maps', fn ($maps): bool => $maps->pluck('id')->all() === [$named->id]);
    $this->get(route('maps.index', ['q' => 'lycian-trail']))->assertOk()
        ->assertViewHas('maps', fn ($maps): bool => $maps->pluck('id')->all() === [$slug->id]);
    $this->get(route('maps.index', ['q' => '_']))->assertOk()
        ->assertViewHas('maps', fn ($maps): bool => $maps->isEmpty());
});

test('my maps validates search queries and requires an authenticated owner', function () {
    $this->getJson(route('maps.index'))->assertUnauthorized();
    $this->actingAs(User::factory()->create());
    foreach ([str_repeat('x', 161), ['invalid']] as $search) {
        $this->getJson(route('maps.index', ['q' => $search]))->assertUnprocessable()->assertJsonValidationErrors('q');
    }
});

test('map pages keep named map editing off the map for guests and owners', function () {
    $owner = User::factory()->create();
    $map = Map::factory()->for($owner)->create();
    $this->get(route('maps.show', $map))->assertOk()
        ->assertSee('Share')
        ->assertDontSee('id="shared-map-details-title"', false)
        ->assertDontSee('id="shared-map-details-description"', false)
        ->assertDontSee('id="shared-map-details-slug"', false);
    $this->actingAs($owner)->get(route('maps.show', $map))->assertOk()
        ->assertSee('Share')
        ->assertDontSee('id="shared-map-details-title"', false)
        ->assertDontSee('id="shared-map-details-description"', false)
        ->assertDontSee('id="shared-map-details-slug"', false);
});

test('map slugs are stored directly and short links are case sensitive', function () {
    $map = Map::factory()->create(['slug' => 'Ab12Cd34']);
    expect(Schema::hasTable('map_slugs'))->toBeFalse()
        ->and($map->slug)->toBe('Ab12Cd34');
    $this->get('/maps/Ab12Cd34')->assertOk();
    $this->getJson('/maps/ab12cd34')->assertNotFound();
});

test('slug availability uses current slugs and only excludes the authenticated owners map', function () {
    $owner = User::factory()->create();
    $map = Map::factory()->for($owner)->create(['slug' => 'reserved-name']);
    $other = Map::factory()->create();
    $endpoint = route('maps.check-slug');

    $this->getJson($endpoint.'?slug=free-name')->assertUnauthorized();
    $this->actingAs($owner)->getJson($endpoint.'?slug=free-name')->assertOk()->assertJsonPath('available', true);
    foreach (['reserved-name', 'ab', 'a--b'] as $slug) {
        $this->getJson($endpoint.'?slug='.$slug)->assertUnprocessable()->assertJsonValidationErrors('slug');
    }
    $this->getJson($endpoint.'?slug=reserved-name&map='.$map->id)->assertOk();
    $this->getJson($endpoint.'?slug=free-name&map='.$other->id)->assertNotFound();
    $this->getJson($endpoint.'?slug=')->assertOk();
    $this->getJson(route('ru.maps.check-slug', ['slug' => 'RESERVED-NAME']))->assertUnprocessable()->assertJsonValidationErrors('slug');
    expect($map->refresh()->slug)->toBe('reserved-name');
});

test('the tracks tab uses compact rows with separate rename and delete controls', function (string $locale) {
    $owner = User::factory()->create(['locale' => $locale]);
    $route = $locale === 'ru' ? 'ru.tracks.index' : 'tracks.index';
    $response = $this->actingAs($owner)->get(route($route))->assertOk()
        ->assertViewHas('activeTab', 'tracks')
        ->assertSee('<title>'.trans('ui.tracks.my_tracks', [], $locale).' — Rodnik.today</title>', false)
        ->assertSee('id="track-library"', false)
        ->assertSee('id="track-search"', false)
        ->assertSee('id="track-delete-dialog"', false)
        ->assertDontSee('id="map-library-list"', false)
        ->assertDontSee('id="map-library-help"', false)
        ->assertDontSee('item.created_at', false)
        ->assertDontSee(trans('ui.tracks.open', locale: $locale));

    $document = HTMLDocument::createFromString($response->getContent(), LIBXML_NOERROR);
    $main = $document->querySelector('main[x-data]');
    $row = HTMLDocument::createFromString($main->querySelector('template[x-for]')->innerHTML, LIBXML_NOERROR);
    $article = $row->querySelector('article');
    $preview = $row->querySelector('[data-track-preview]');
    $title = $row->querySelector('a[x-text="item.name"]');
    $metadata = $row->querySelector('[data-track-metadata]');
    $menu = $row->querySelector('[x-show="menuToken === item.token"]');
    $rename = HTMLDocument::createFromString($row->querySelector('template[x-if="editingToken === item.token"]')->innerHTML, LIBXML_NOERROR)->querySelector('form');
    expect($main->getAttribute('x-data'))->toStartWith('trackLibrary(')
        ->and($main->classList->contains('font-sans'))->toBeTrue()
        ->and($main->querySelector('nav a[aria-current="page"]')->getAttribute('href'))->toBe(route($route))
        ->and($article->classList->contains('px-3'))->toBeTrue()
        ->and($article->classList->contains('py-3'))->toBeTrue()
        ->and($article->classList->contains('sm:px-5'))->toBeTrue()
        ->and($row->querySelectorAll('a')->length)->toBe(3)
        ->and($article->classList->contains('sm:grid-cols-[5rem_minmax(0,1fr)_auto]'))->toBeTrue()
        ->and($preview->getAttribute(':href'))->toBe('item.url')
        ->and($preview->getAttribute('tabindex'))->toBe('-1')
        ->and($preview->classList->contains('hidden'))->toBeTrue()
        ->and($preview->classList->contains('sm:block'))->toBeTrue()
        ->and($preview->querySelector('svg'))->not->toBeNull()
        ->and($title->getAttribute(':href'))->toBe('item.url')
        ->and($title->classList->contains('text-base'))->toBeTrue()
        ->and($title->classList->contains('font-semibold'))->toBeTrue()
        ->and($title->classList->contains('wrap-anywhere'))->toBeTrue()
        ->and($title->classList->contains('block'))->toBeFalse()
        ->and($title->classList->contains('inline-block'))->toBeFalse()
        ->and($title->classList->contains('flex'))->toBeFalse()
        ->and(preg_match('/(?:^|\\s)(?:[a-z]+:)?p[xytrlbse]?-/', $title->getAttribute('class')))->toBe(0)
        ->and($title->parentElement->localName)->toBe('div')
        ->and($title->parentElement->hasAttribute('@click'))->toBeFalse()
        ->and($title->parentElement->classList->contains('pr-10'))->toBeTrue()
        ->and($metadata->classList->contains('text-sm'))->toBeTrue()
        ->and($metadata->classList->contains('text-zinc-500'))->toBeTrue()
        ->and($metadata->classList->contains('flex-wrap'))->toBeTrue()
        ->and($metadata->querySelector('span[x-text="used(item)"]'))->not->toBeNull()
        ->and($metadata->querySelector('span[x-text*="distance(item.distance_km)"]')->getAttribute('x-show'))->toBe('Number.isFinite(item.distance_km)')
        ->and($metadata->querySelector('[aria-hidden="true"]')->getAttribute('x-show'))->toBe('Number.isFinite(item.distance_km)')
        ->and($row->querySelector('[data-track-actions]')->classList->contains('absolute'))->toBeTrue()
        ->and($row->querySelector('[data-track-actions]')->classList->contains('sm:relative'))->toBeTrue()
        ->and($menu->querySelectorAll('button')->length)->toBe(2)
        ->and($menu->querySelector('button')->getAttribute('@click'))->toStartWith('rename(item,')
        ->and($menu->querySelector('a')->getAttribute(':href'))->toBe('item.download_url')
        ->and($menu->querySelector('button[aria-controls="track-delete-dialog"]')->getAttribute('@click'))->toStartWith('openDelete(item,')
        ->and($rename->getAttribute('@submit.prevent'))->toBe('saveName(item)')
        ->and($rename->classList->contains('col-span-full'))->toBeTrue()
        ->and($rename->querySelector('input')->getAttribute('x-model'))->toBe('draftName')
        ->and($rename->querySelector('input')->getAttribute('maxlength'))->toBe('160')
        ->and($rename->querySelector('input')->hasAttribute('required'))->toBeTrue()
        ->and($rename->querySelector('button[type="button"]')->getAttribute('@click'))->toBe('cancelRename()');
})->with(['en', 'ru']);
