<?php

declare(strict_types=1);

use App\Actions\StoreMapAction;
use App\Models\Map;
use App\Models\Track;
use App\Models\User;
use App\Support\MapPayload;
use Dom\HTMLDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

test('map creation requires an account and creates an owned saved view', function (bool $authenticated) {
    $owner = User::factory()->create();
    if ($authenticated) {
        $this->actingAs($owner);
    }

    if (! $authenticated) {
        $this->postJson(route('maps.store'), ['title' => 'Sunday springs', 'state' => app(MapPayload::class)->defaultState(), 'track_token' => null])->assertUnauthorized();
        $this->assertDatabaseEmpty('maps');

        return;
    }

    $response = $this->postJson(route('maps.store'), [
        'title' => 'Sunday springs', 'state' => app(MapPayload::class)->defaultState(), 'track_token' => null,
        'user_id' => $owner->id, 'is_starred' => true,
    ])->assertCreated()->assertJsonPath('can_update', $authenticated);
    $map = Map::query()->sole();

    $response->assertJsonPath('id', $map->id)->assertJsonPath('url', url('/maps/'.$map->slug).'/');
    expect($map->user_id)->toBe($authenticated ? $owner->id : null)
        ->and($map->is_starred)->toBeFalse()->and($map->title)->toBe('Sunday springs');
    if ($authenticated) {
        $response->assertJsonPath('edit_url', route('maps.edit', ['map' => $map->id]))
            ->assertJsonPath('starred', false);
        $this->get(route('maps.index'))->assertViewHas('maps', fn ($maps): bool => $maps->contains($map));
    } else {
        $response->assertJsonMissingPath('edit_url')->assertJsonMissingPath('starred');
    }
    $response->assertJsonMissingPath('token');
    $this->getJson($response->json('url'))->assertOk()->assertJsonPath('id', $map->id);
})->with([false, true]);

test('direct editing selects an owned map outside the current collection page and search', function (string $prefix) {
    $owner = User::factory()->create();
    $map = Map::factory()->for($owner)->create(['title' => 'Old route', 'created_at' => now()->subYear(), 'updated_at' => now()->subYear()]);
    Map::factory()->for($owner)->count(21)->create(['title' => 'Latest routes']);

    $this->actingAs($owner)->get($prefix.'/user/maps/'.$map->id.'/edit?q=Latest&page=2')
        ->assertOk()->assertViewIs('maps.index')
        ->assertViewHas('editingMap', fn (Map $editing): bool => $editing->is($map))
        ->assertViewHas('maps', fn ($maps): bool => $maps->count() === 1 && ! $maps->contains($map))
        ->assertSee('x-show="inlineEditing"', false)->assertSee('@submit.prevent="saveInline(true)"', false)
        ->assertSee('x-model="draftTitle"', false)->assertSee('x-model="draftSlug"', false)
        ->assertSee('id="map-editor-dialog"', false)->assertDontSee('id="map-editor-view-url"', false)
        ->assertDontSee('id="map-editor-description"', false);
    $this->get($prefix.'/user/maps')->assertOk()->assertViewHas('editingMap', null);
    expect($map->publicData($owner, $prefix === '/ru' ? 'ru' : 'en')['edit_url'])
        ->toBe(url($prefix.'/user/maps/'.$map->id.'/edit'));
})->with(['', '/ru']);

test('map editing starring and deletion are restricted to the owner', function (bool $anonymous) {
    $owner = User::factory()->create();
    $map = Map::factory()->create(['user_id' => $anonymous ? null : $owner->id]);
    $edit = route('maps.edit', ['map' => $map->id]);
    $star = route('maps.star', ['map' => $map->id]);
    $destroy = route('maps.destroy', ['map' => $map->id]);

    $this->getJson($edit)->assertUnauthorized();
    $this->patchJson($star, ['starred' => true])->assertUnauthorized();
    $this->deleteJson($destroy)->assertUnauthorized();
    $this->actingAs(User::factory()->create());
    $this->getJson($edit)->assertForbidden();
    $this->patchJson($star, ['starred' => true])->assertForbidden();
    $this->deleteJson($destroy)->assertForbidden();
    $this->getJson(route('maps.show', ['map' => $map->slug]))->assertOk()
        ->assertJsonMissingPath('edit_url')->assertJsonMissingPath('starred');
    $this->assertModelExists($map);
    expect($map->refresh()->is_starred)->toBeFalse();
})->with([false, true]);

test('map editor metadata identifies the localized private editor page', function (string $prefix, string $locale) {
    $owner = User::factory()->create();
    $map = Map::factory()->for($owner)->create();
    $path = $prefix.'/user/maps/'.$map->id.'/edit';
    $response = $this->actingAs($owner)->get($path.'?utm_source=link')
        ->assertOk()->assertHeader('X-Robots-Tag', 'noindex');
    $document = HTMLDocument::createFromString($response->getContent(), LIBXML_NOERROR);
    $title = trans('ui.maps.edit_map', locale: $locale).' — Rodnik.today';

    expect($document->querySelector('title')->textContent)->toBe($title)
        ->and($document->querySelector('meta[property="og:title"]')->getAttribute('content'))->toBe($title)
        ->and($document->querySelector('link[rel="canonical"]')->getAttribute('href'))->toBe(url($path))
        ->and($document->querySelector('meta[property="og:url"]')->getAttribute('content'))->toBe(url($path))
        ->and($document->querySelector('meta[name="robots"]')->getAttribute('content'))->toBe('noindex, nofollow')
        ->and($document->querySelector('link[hreflang]'))->toBeNull();
})->with(['English' => ['', 'en'], 'Russian' => ['/ru', 'ru']]);

test('starring and editing maps preserve newest shared order with stable ties', function () {
    $this->freezeTime();
    $owner = User::factory()->create();
    $old = Map::factory()->for($owner)->create(['created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2)]);
    $recent = Map::factory()->for($owner)->create(['created_at' => now()->subDay(), 'updated_at' => now()->subDay()]);
    $tied = Map::factory()->for($owner)->create(['created_at' => $recent->created_at, 'updated_at' => now()->subDays(3)]);
    $expectedOrder = [$tied->id, $recent->id, $old->id];
    $original = $old->getAttributes();
    $this->actingAs($owner)->patchJson(route('maps.star', ['map' => $old->id]), ['starred' => true])
        ->assertOk()->assertJsonPath('starred', true)->assertJsonPath('version', 1);
    expect($old->refresh()->updated_at->equalTo($original['updated_at']))->toBeTrue();
    $this->get(route('maps.index'))->assertViewHas('maps', fn ($maps): bool => $maps->modelKeys() === $expectedOrder);
    $this->getJson(route('maps.options'))->assertJsonPath('data.*.id', $expectedOrder);

    $this->patchJson(route('maps.details', $old), ['title' => 'Updated title', 'version' => 1])->assertOk();
    expect($old->refresh()->updated_at->greaterThan($recent->updated_at))->toBeTrue();
    $this->get(route('maps.index'))->assertViewHas('maps', fn ($maps): bool => $maps->modelKeys() === $expectedOrder);
    $this->getJson(route('maps.options'))->assertJsonPath('data.*.id', $expectedOrder);
    $this->patchJson(route('maps.star', ['map' => $old->id]), ['starred' => false])
        ->assertOk()->assertJsonPath('starred', false)->assertJsonPath('version', 2);
    $this->get(route('maps.index'))->assertViewHas('maps', fn ($maps): bool => $maps->modelKeys() === $expectedOrder);
    $this->getJson(route('maps.options'))->assertJsonPath('data.*.id', $expectedOrder);
});

test('star values must be explicit booleans', function (mixed $starred) {
    $owner = User::factory()->create();
    $map = Map::factory()->for($owner)->create();
    $this->actingAs($owner)->patchJson(route('maps.star', ['map' => $map->id]), ['starred' => $starred])
        ->assertUnprocessable()->assertJsonValidationErrors('starred');
    expect($map->refresh()->is_starred)->toBeFalse();
})->with([[null], [1], ['true'], [[]]]);

test('favorites and search filter only owned maps and retain both filters during pagination', function (string $prefix) {
    $owner = User::factory()->create();
    $matches = Map::factory()->for($owner)->count(21)->create(['title' => 'Lycian Water', 'is_starred' => true]);
    Map::factory()->for($owner)->create(['title' => 'Lycian Water unstarred', 'is_starred' => false]);
    Map::factory()->for($owner)->create(['title' => 'Other favorite', 'is_starred' => true]);
    $foreign = Map::factory()->for(User::factory())->create(['title' => 'Lycian Water foreign', 'is_starred' => true]);

    $response = $this->actingAs($owner)->get($prefix.'/user/maps?q=Lycian&favorites=1')
        ->assertOk()->assertViewHas('favorites', true)->assertViewHas('search', 'Lycian')
        ->assertDontSee($foreign->slug);
    $maps = $response->viewData('maps');
    expect($maps->total())->toBe(21)->and($maps)->toHaveCount(20)
        ->and($maps->pluck('id')->diff($matches->modelKeys()))->toHaveCount(0);
    $this->get($maps->nextPageUrl())->assertOk()->assertViewHas('favorites', true)->assertViewHas('search', 'Lycian')
        ->assertViewHas('maps', fn ($page): bool => $page->count() === 1 && $page->total() === 21);

    $options = $this->getJson(route($prefix === '/ru' ? 'ru.maps.options' : 'maps.options', ['q' => 'Lycian', 'favorites' => 1]))->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')->assertJsonCount(20, 'data')
        ->assertJsonPath('current_page', 1)->assertJsonPath('last_page', 2)->assertJsonPath('total', 21);
    foreach ($options->json('data') as $item) {
        expect($item['starred'])->toBeTrue()->and($item['can_update'])->toBeTrue()
            ->and($item)->not->toHaveKey('description')->and($item)->not->toHaveKey('user_id');
    }
    $next = $options->json('next_page_url');
    expect($next)->toContain('q=Lycian')->toContain('favorites=1');
    $this->getJson($next)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('current_page', 2)
        ->assertJsonPath('total', 21)->assertJsonPath('next_page_url', null);
})->with(['English' => '', 'Russian' => '/ru']);

test('turning off favorites restores unstarred maps and live options expose only owner action URLs', function () {
    $owner = User::factory()->create();
    $favorite = Map::factory()->for($owner)->create(['is_starred' => true]);
    $ordinary = Map::factory()->for($owner)->create(['is_starred' => false]);
    $this->actingAs($owner)->get(route('maps.index', ['favorites' => 0]))->assertOk()
        ->assertViewHas('favorites', false)->assertViewHas('maps', fn ($maps): bool => $maps->total() === 2);
    $response = $this->getJson(route('maps.options', ['favorites' => 0]))->assertOk()->assertJsonCount(2, 'data');
    $response->assertJsonPath('data.0.id', $ordinary->id);
    $first = $response->json('data.1');
    expect($first['id'])->toBe($favorite->id)
        ->and($first['created_at'])->toBe($favorite->created_at->toIso8601String())
        ->and($first['rename_url'])->toBe(route('maps.rename', $favorite))
        ->and($first['details_url'])->toBe(route('maps.details', $favorite))
        ->and($first['star_url'])->toBe(route('maps.star', ['map' => $favorite->id]))
        ->and($first['delete_url'])->toBe(route('maps.destroy', ['map' => $favorite->id]));
    $this->getJson(route('maps.options', ['favorites' => 1]))->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $favorite->id);
    $this->getJson(route('maps.show', $ordinary))->assertOk()->assertJsonMissingPath('delete_url')
        ->assertJsonMissingPath('details_url')->assertJsonMissingPath('rename_url')->assertJsonMissingPath('star_url');
});

test('favorites filters reject malformed values without exposing another users collection', function (mixed $favorites) {
    $this->actingAs(User::factory()->create());
    foreach (['maps.index', 'maps.options'] as $route) {
        $this->getJson(route($route, ['favorites' => $favorites]))->assertUnprocessable()->assertJsonValidationErrors('favorites');
    }
})->with([[2], ['true'], [['1']]]);

test('deleting a map revokes every map URL while keeping its uploaded track', function (bool $copied) {
    $owner = User::factory()->create();
    $track = Track::factory()->for($owner)->create();
    $map = Map::factory()->for($owner)->create(['slug' => 'deleted-route', 'track_id' => $track->id]);
    $copy = $copied ? Map::factory()->for(User::factory())->create(['track_id' => $track->id]) : null;

    $this->actingAs($owner)->deleteJson(route('maps.destroy', ['map' => $map->id]))->assertNoContent();
    $this->assertModelMissing($map);
    foreach (['/maps/deleted-route', '/ru/maps/deleted-route'] as $url) {
        $this->getJson($url)->assertNotFound();
        $this->get($url)->assertNotFound()->assertViewHas('missingMap', true)->assertViewHas('sharedMap', null);
    }
    if ($copied) {
        $this->assertModelExists($track);
        expect($copy->refresh()->track_id)->toBe($track->id);
        $this->actingAs($copy->user)->deleteJson(route('maps.destroy', ['map' => $copy->id]))->assertNoContent();
    }
    $this->assertModelExists($track);
})->with([false, true]);

test('a track removed after payload validation cannot produce a saved map without its track', function () {
    $track = Track::factory()->create();
    $store = app(StoreMapAction::class);
    $validated = $store->validate(['title' => 'Sunday springs', 'state' => app(MapPayload::class)->defaultState(), 'track_token' => $track->token]);
    $track->delete();

    expect(fn () => $store->execute(null, $validated))->toThrow(ValidationException::class);
    $this->assertDatabaseEmpty('maps');
});

test('replacing a saved track preserves both uploaded tracks independently of maps', function (string $route, bool $copied) {
    $owner = User::factory()->create();
    $original = Track::factory()->for($owner)->create();
    $replacement = Track::factory()->for($owner)->create();
    $map = Map::factory()->for($owner)->create(['track_id' => $original->id]);
    $copy = $copied ? Map::factory()->for(User::factory())->create(['track_id' => $original->id]) : null;
    $payload = ['title' => $map->title, 'state' => $map->state, 'track_token' => $replacement->token, 'version' => 1];

    $this->actingAs($owner)->patchJson(route($route, $map), $payload)->assertOk();
    expect($map->refresh()->track_id)->toBe($replacement->id);
    if ($copied) {
        $this->assertModelExists($original);
        expect($copy->refresh()->track_id)->toBe($original->id);
    } else {
        $this->assertModelExists($original);
    }
    $this->patchJson(route($route, $map), array_replace($payload, ['track_token' => null, 'version' => 2]))->assertOk();
    $this->assertModelExists($replacement);
    expect($map->refresh()->track_id)->toBeNull();
})->with(['maps.update', 'maps.details'])->with([false, true]);

test('map libraries search Unicode names and ASCII link names without collation errors', function (string $prefix, string $search, string $expected) {
    $owner = User::factory()->create();
    $named = Map::factory()->for($owner)->create(['title' => 'Поход к родникам 💧', 'slug' => 'forest-springs']);
    $linked = Map::factory()->for($owner)->create(['title' => 'Прогулка у моря', 'slug' => 'coastal-water']);
    Map::factory()->for(User::factory())->create(['title' => $named->title, 'slug' => 'other-coastal-water']);
    $expectedMap = $expected === 'name' ? $named : $linked;
    $query = http_build_query(['q' => $search]);

    $this->actingAs($owner)->get($prefix.'/user/maps?'.$query)->assertOk()
        ->assertViewHas('maps', fn ($maps): bool => $maps->modelKeys() === [$expectedMap->id]);
    $this->getJson($prefix.'/user/maps/options?'.$query)->assertOk()
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $expectedMap->id);
})->with(['English' => '', 'Russian' => '/ru'])->with([
    'Cyrillic name' => ['родникам', 'name'],
    'uppercase Cyrillic name' => ['РОДНИКАМ', 'name'],
    'emoji in a name' => ['💧', 'name'],
    'Latin link name' => ['coastal', 'link'],
]);

test('slug availability rejects Unicode map references before querying ASCII identifiers', function (string $prefix) {
    $owner = User::factory()->create();
    $map = Map::factory()->for($owner)->create(['slug' => 'existing-route']);
    $this->actingAs($owner)->getJson($prefix.'/user/maps/check-slug?'.http_build_query([
        'slug' => 'free-route', 'map' => 'маршруты',
    ]))->assertUnprocessable()->assertJsonValidationErrors('map');
    $this->getJson($prefix.'/user/maps/check-slug?'.http_build_query([
        'slug' => 'маршрут', 'map' => $map->id,
    ]))->assertUnprocessable()->assertJsonValidationErrors('slug');
})->with(['English' => '', 'Russian' => '/ru']);

test('map link validation uses localized guidance across availability creation and editing', function (string $locale, mixed $slug, string $message) {
    $owner = User::factory()->create(['locale' => $locale === 'ru' ? 'en' : 'ru']);
    Map::factory()->for($owner)->create(['slug' => 'reserved-link']);
    $map = Map::factory()->for($owner)->create();
    $expected = __('ui.maps.'.$message, locale: $locale);
    $this->actingAs($owner)->withHeader('X-Rodnik-Locale', $locale);
    $this->getJson(route($locale === 'ru' ? 'ru.maps.check-slug' : 'maps.check-slug', ['slug' => $slug]))
        ->assertUnprocessable()->assertJsonPath('errors.slug.0', $expected);
    $this->postJson(route('maps.store'), ['title' => 'Sunday springs', 'state' => app(MapPayload::class)->defaultState(), 'track_token' => null, 'slug' => $slug])
        ->assertUnprocessable()->assertJsonPath('errors.slug.0', $expected);
    $this->patchJson(route('maps.details', $map), ['version' => 1, 'slug' => $slug])
        ->assertUnprocessable()->assertJsonPath('errors.slug.0', $expected);
})->with(['en', 'ru'])->with([
    'already used' => ['reserved-link', 'link_unavailable'],
    'too short' => ['ab', 'link_invalid'],
    'too long' => [str_repeat('a', 81), 'link_invalid'],
    'Cyrillic' => ['маршрут', 'link_invalid'],
    'invalid shape' => [['unexpected'], 'link_invalid'],
]);

test('concurrent map link claims keep localized warnings when the database rejects the save', function (string $locale) {
    $owner = User::factory()->create();
    $map = Map::factory()->for($owner)->create();
    $originalSlug = $map->slug;
    $store = app(StoreMapAction::class);
    $update = app(App\Actions\UpdateMapDetailsAction::class);
    $creation = $store->validate(['title' => 'Sunday springs', 'state' => app(MapPayload::class)->defaultState(), 'track_token' => null, 'slug' => 'new-link'], $locale);
    $editing = $update->validate($map, ['version' => 1, 'slug' => 'other-link'], $locale);
    Map::factory()->for($owner)->create(['slug' => 'new-link']);
    Map::factory()->for($owner)->create(['slug' => 'other-link']);
    app()->setLocale($locale === 'ru' ? 'en' : 'ru');
    $expected = __('ui.maps.link_unavailable', locale: $locale);

    expect(fn () => $store->execute($owner, $creation, $locale))->toThrow(ValidationException::class, $expected)
        ->and(fn () => $update->execute($owner, $map, $editing, $locale))->toThrow(ValidationException::class, $expected);
    expect($map->refresh()->version)->toBe(1)->and($map->slug)->toBe($originalSlug);
})->with(['en', 'ru']);
