<?php

declare(strict_types=1);

use App\Livewire\Duo;
use App\Models\Map;
use App\Models\Spring;
use App\Models\Track;
use App\Models\User;
use Dom\HTMLDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Js;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('share routes are removed for guests and signed in users in both languages', function (string $prefix, bool $authenticated) {
    $owner = User::factory()->create();
    $map = Map::factory()->for($owner)->create();
    if ($authenticated) {
        $this->actingAs($owner);
    }

    $this->get($prefix.'/share/'.$map->slug)->assertNotFound();
    $this->getJson($prefix.'/share/'.$map->slug)->assertNotFound();
    $this->get($prefix.'/share/'.$map->slug.'/login')->assertNotFound()->assertSessionMissing('url.intended');
    $this->postJson($prefix.'/share', [
        'title' => 'Removed endpoint', 'state' => $map->state, 'track_token' => null,
    ])->assertMethodNotAllowed();

    expect(collect(Route::getRoutes())->filter(fn ($route): bool => preg_match('#^(?:ru/)?share(?:/|$)#', $route->uri()) === 1))->toBeEmpty()
        ->and($map->refresh()->views_count)->toBe(0);
    $this->assertDatabaseCount('maps', 1);
})->with(['English' => '', 'Russian' => '/ru'])->with(['guest' => false, 'signed in' => true]);

test('missing saved maps render a usable localized map with a dismissible notice', function (string $locale, string $prefix, string $path, bool $navigating) {
    if ($navigating) {
        $this->withHeader('X-Livewire-Navigate', 'true');
    }

    $response = $this->get($prefix.$path)->assertNotFound()->assertViewIs('duo')
        ->assertViewHas('missingMap', true)->assertViewHas('sharedMap', null)
        ->assertViewHas('page', ['spring' => null, 'user' => null, 'location' => null])
        ->assertHeader('X-Robots-Tag', 'noindex')
        ->assertSee('id="map"', false)->assertSee('data-rodnik-page', false)
        ->assertSee('id="missing-map-dialog"', false)
        ->assertSee(trans('ui.maps.not_found', locale: $locale));
    $document = HTMLDocument::createFromString($response->getContent(), LIBXML_NOERROR);

    expect($response->headers->hasCacheControlDirective('no-store'))->toBeTrue()
        ->and($response->headers->hasCacheControlDirective('private'))->toBeTrue()
        ->and($document->documentElement->getAttribute('lang'))->toBe($locale)
        ->and($document->querySelector('link[rel="canonical"]')->getAttribute('href'))->toBe(url('/').($prefix ?: '/'))
        ->and($document->querySelector('meta[name="robots"]')->getAttribute('content'))->toBe('noindex, nofollow')
        ->and($document->querySelector('link[hreflang]'))->toBeNull()
        ->and(json_decode($document->querySelector('#rodnik-shared-map')->textContent, true, flags: JSON_THROW_ON_ERROR))->toBeNull();
    $this->assertDatabaseEmpty('maps');
})->with(['English' => ['en', ''], 'Russian' => ['ru', '/ru']])
    ->with(['named URL' => '/maps/missing-map/', 'short URL' => '/maps/L6gFlsOMff/'])
    ->with(['page load' => false, 'Livewire navigation' => true]);

test('missing saved maps stay not found to JSON resolvers', function (string $locale, string $prefix, string $path) {
    $this->getJson($prefix.$path)->assertNotFound()
        ->assertExactJson(['message' => trans('ui.maps.not_found', locale: $locale)])
        ->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Robots-Tag', 'noindex');
    $this->assertDatabaseEmpty('maps');
})->with(['English' => ['en', ''], 'Russian' => ['ru', '/ru']])
    ->with(['named URL' => '/maps/missing-map/', 'short URL' => '/maps/L6gFlsOMff/']);

test('missing saved maps ignore unrelated resource query data and never expose another map', function () {
    $owner = User::factory()->create();
    $map = Map::factory()->for($owner)->create(['title' => 'Unrelated saved-map title 4fc2']);
    $before = $map->refresh()->getAttributes();

    $this->actingAs($owner)->get('/maps/L6gFlsOMff/?spring=not-a-number&user[]=123&location=1')
        ->assertNotFound()->assertViewHas('missingMap', true)->assertViewHas('sharedMap', null)
        ->assertViewHas('page', ['spring' => null, 'user' => null, 'location' => null])
        ->assertDontSee($map->title)->assertDontSee($map->slug);

    expect($map->refresh()->getAttributes())->toEqual($before);
    $this->assertDatabaseCount('maps', 1);
});

test('missing map edit login and mutation routes keep ordinary not found responses', function () {
    $this->actingAs(User::factory()->create());
    foreach (['', '/ru'] as $prefix) {
        foreach (['/maps/missing-map/login', '/share/Missing1/login', '/user/maps/999999999/edit'] as $path) {
            $this->get($prefix.$path)->assertNotFound()->assertDontSee('id="missing-map-dialog"', false);
        }
        $this->patchJson($prefix.'/user/maps/999999999/star', ['starred' => true])->assertNotFound();
        $this->deleteJson($prefix.'/user/maps/999999999')->assertNotFound();
    }
    foreach (['/maps/999999999', '/maps/999999999/title', '/maps/999999999/details'] as $path) {
        $this->patchJson($path, ['version' => 1])->assertNotFound();
    }
    $this->assertDatabaseEmpty('maps');
});

test('shared pages include the saved view without inlining the track geometry', function () {
    $track = Track::factory()->create();
    $map = Map::factory()->create(['track_id' => $track->id]);

    $this->get(route('maps.show', $map))
        ->assertOk()
        ->assertSee('id="rodnik-shared-map"', false)
        ->assertSee($map->slug)
        ->assertSee($track->hash)
        ->assertDontSee('FeatureCollection')
        ->assertSee('Share')
        ->assertSee('tracks-url');
});

test('removed map descriptions never appear in shared HTML JSON or metadata', function (string $viewer, string $prefix) {
    $owner = User::factory()->create();
    $map = Map::factory()->for($owner)->create(['slug' => 'shared-view-map']);
    if ($viewer !== 'guest') {
        $this->actingAs($viewer === 'owner' ? $owner : User::factory()->create());
    }

    foreach ([$prefix.'/maps/'.$map->slug.'/', $prefix.'/maps/'.$map->slug] as $url) {
        $this->getJson($url)->assertOk()->assertJsonMissingPath('description');
        $response = $this->get($url)->assertOk()->assertDontSee('map-editor-description')
            ->assertViewHas('sharedMap', fn (array $data): bool => ! array_key_exists('description', $data));
        $document = HTMLDocument::createFromString($response->getContent(), LIBXML_NOERROR);
        $sharedMap = json_decode($document->querySelector('#rodnik-shared-map')->textContent, true, flags: JSON_THROW_ON_ERROR);

        expect($sharedMap)->not->toHaveKey('description')
            ->and($document->querySelector('meta[name="description"]')->getAttribute('content'))->not->toBeEmpty()
            ->and($document->querySelector('meta[property="og:description"]')->getAttribute('content'))->not->toBeEmpty();
    }
})->with(['guest', 'other user', 'owner'])->with(['English' => '', 'Russian' => '/ru']);

test('map descriptions are absent from model attributes and every serializer', function () {
    $owner = User::factory()->create();
    $map = Map::factory()->for($owner)->create();

    expect($map->getAttributes())->not->toHaveKey('description')
        ->and($map->toArray())->not->toHaveKey('description')
        ->and($map->toJson())->not->toContain('"description"')
        ->and($owner->load('maps')->toArray()['maps'][0])->not->toHaveKey('description')
        ->and($map->ownerData($owner))->not->toHaveKey('description')
        ->and($map->publicData($owner))->not->toHaveKey('description');
});

test('saving another owners shared map copies its view without copying its name or obsolete description', function (bool $customSlug) {
    $owner = User::factory()->create();
    $map = Map::factory()->for($owner)->create(['title' => 'Original route 4fc2']);
    $visitor = User::factory()->create();
    $shared = $this->actingAs($visitor)->getJson(route('maps.show', $map))->assertOk()
        ->assertJsonMissingPath('description')->json();

    $response = $this->postJson(route('maps.store'), [
        'slug' => $customSlug ? 'my-route-copy' : null,
        'title' => 'My route copy',
        'state' => $shared['state'], 'track_token' => $shared['track']['token'] ?? null,
        'description' => 'Obsolete note 4fc2',
    ])->assertCreated()->assertDontSee('Obsolete note 4fc2')->assertJsonMissingPath('description');
    $copy = $visitor->maps()->sole();

    expect($copy->getAttributes())->not->toHaveKey('description')->and($copy->state)->toEqual($map->state)
        ->and($copy->slug)->not->toBe($map->slug)
        ->and($copy->title)->not->toBe($map->title)
        ->and($map->refresh()->title)->toBe('Original route 4fc2');
    $this->getJson($response->json('url'))->assertOk()->assertJsonMissingPath('description');
})->with(['custom URL' => true, 'generated URL' => false]);

test('pasted public map links resolve to JSON without recording a page view', function (string $prefix, bool $named) {
    $track = Track::factory()->create();
    $owner = User::factory()->create();
    $state = Map::factory()->make()->state;
    $state['page'] = ['spring' => 987654321, 'user' => 987654321, 'location' => 1];
    $map = Map::factory()->create([
        'user_id' => $named ? $owner->id : null, 'state' => $state, 'track_id' => $track->id,
        'slug' => 'lycian-way', 'views_count' => 9,
    ]);
    $path = '/maps/'.$map->slug.'/';
    $before = $map->refresh()->getAttributes();

    $this->getJson($prefix.$path)->assertOk()->assertHeader('X-Robots-Tag', 'noindex')
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('id', $map->id)->assertJsonMissingPath('token')->assertJsonPath('track.hash', $track->hash)
        ->assertJsonPath('state.center', $state['center'])
        ->assertJsonPath('state.page.spring', null)
        ->assertJsonPath('state.page.user', null)
        ->assertJsonPath('state.page.location', null)
        ->assertJsonPath('resource_url', url('/').($prefix ?: '/'))
        ->assertJsonMissingPath('user_id')->assertJsonMissingPath('views_count');

    expect($map->refresh()->getAttributes())->toEqual($before);
})->with(['English' => '', 'Russian' => '/ru'])->with(['anonymous' => false, 'named' => true]);

test('shared pages use normal resource metadata while retaining the saved map for editing', function (string $locale, string $prefix, string $resource) {
    $owner = User::factory()->create(['name' => 'River walker']);
    $spring = $resource === 'spring' ? Spring::factory()->create(['name' => 'Forest spring']) : null;
    $state = Map::factory()->make()->state;
    $state['page']['spring'] = $spring?->id;
    $state['page']['user'] = $resource === 'user' ? $owner->id : null;
    $map = Map::factory()->for($owner)->create(['title' => 'Saved weekend route', 'state' => $state]);
    $path = match ($resource) {
        'spring' => '/'.$spring->id.'/',
        'user' => '/users/'.$owner->id.'/',
        default => '/',
    };
    $canonical = url('/').$prefix.($path === '/' && $prefix !== '' ? '' : $path);
    $title = match ($resource) {
        'spring' => 'Forest spring — Rodnik.today',
        'user' => 'River walker — Rodnik.today',
        default => trans('seo.default_title', locale: $locale),
    };

    $response = $this->actingAs($owner)->get($prefix.'/maps/'.$map->slug)
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex');
    $document = HTMLDocument::createFromString($response->getContent(), LIBXML_NOERROR);
    $saved = json_decode($document->querySelector('#rodnik-shared-map')->textContent, true, flags: JSON_THROW_ON_ERROR);

    expect($document->querySelector('title')->textContent)->toBe($title)
        ->and($document->querySelector('link[rel="canonical"]')->getAttribute('href'))->toBe($canonical)
        ->and($document->querySelector('meta[property="og:url"]')->getAttribute('content'))->toBe($canonical)
        ->and($document->querySelector('meta[name="robots"]')->getAttribute('content'))->toBe('noindex, nofollow')
        ->and($document->querySelector('link[hreflang]'))->toBeNull()
        ->and($saved['id'])->toBe($map->id)->and($saved)->not->toHaveKey('token')
        ->and($saved['title'])->toBe($map->title)
        ->and($saved['can_update'])->toBeTrue();
})->with([
    'English spring' => ['en', '', 'spring'],
    'Russian spring' => ['ru', '/ru', 'spring'],
    'English user' => ['en', '', 'user'],
    'Russian user' => ['ru', '/ru', 'user'],
    'English map' => ['en', '', 'map'],
    'Russian map' => ['ru', '/ru', 'map'],
]);

test('shared page metadata uses the surviving page when a saved resource is unavailable', function (string $locale, string $prefix) {
    $state = Map::factory()->make()->state;
    $state['page'] = ['spring' => 987654321, 'user' => 987654321, 'location' => 1];
    $map = Map::factory()->create(['state' => $state]);

    $response = $this->get($prefix.'/maps/'.$map->slug)
        ->assertOk()
        ->assertViewHas('sharedMap', fn (array $sharedMap): bool => $sharedMap['state']['page']['spring'] === null
            && $sharedMap['state']['page']['user'] === null
            && $sharedMap['state']['page']['location'] === null
            && $sharedMap['resource_url'] === url('/').($prefix ?: '/'));
    $document = HTMLDocument::createFromString($response->getContent(), LIBXML_NOERROR);

    expect($document->querySelector('title')->textContent)->toBe(trans('seo.default_title', locale: $locale))
        ->and($document->querySelector('link[rel="canonical"]')->getAttribute('href'))->toBe(url('/').($prefix ?: '/'));
})->with(['English' => ['en', ''], 'Russian' => ['ru', '/ru']]);

test('expanded shared resource URLs retain normalized page context and unrelated query parameters', function (string $prefix) {
    $spring = Spring::factory()->create();
    $user = User::factory()->create();
    $state = Map::factory()->make()->state;
    $state['page'] = ['spring' => $spring->id, 'user' => $user->id, 'location' => 1];
    $map = Map::factory()->create(['state' => $state]);

    $response = $this->get($prefix.'/maps/'.$map->slug.'?user=987654321&spring=987654321&location=0&foo=bar&redirect=false')
        ->assertOk();
    $resourceUrl = $response->viewData('sharedMap')['resource_url'];

    expect($resourceUrl)->toBe(url('/').$prefix.'/'.$spring->id.'/?foo=bar&user='.$user->id.'&location=1');
    $this->get($resourceUrl)
        ->assertOk()
        ->assertViewHas('page', fn (array $page): bool => $page['spring'] === $spring->id
            && $page['user'] === $user->id && $page['location'] === 1);
})->with(['English' => '', 'Russian' => '/ru']);

test('expanded shared links preserve merged source inspection after reloading the normal URL', function (string $prefix) {
    $target = Spring::factory()->create();
    $spring = Spring::factory()->create(['redirect_to_spring_id' => $target->id]);
    $state = Map::factory()->make()->state;
    $state['page']['spring'] = $spring->id;
    $map = Map::factory()->create(['state' => $state]);

    $response = $this->get($prefix.'/maps/'.$map->slug.'?foo=bar&redirect=true')
        ->assertOk();
    $sharedMap = $response->viewData('sharedMap');

    expect($sharedMap['resource_url'])->toBe(url('/').$prefix.'/'.$spring->id.'/?foo=bar&redirect=false')
        ->and($sharedMap['state']['page']['spring'])->toBe($spring->id);
    $this->get($sharedMap['resource_url'])
        ->assertOk()
        ->assertViewHas('spring', fn (Spring $selected): bool => $selected->is($spring))
        ->assertViewHas('page', fn (array $page): bool => $page['spring'] === $spring->id);
})->with(['English' => '', 'Russian' => '/ru']);

test('navigation responses include localized page data in the body that Livewire replaces', function (string $locale, string $prefix) {
    $map = Map::factory()->create(['title' => 'A different map']);
    $response = $this->withHeader('X-Livewire-Navigate', 'true')
        ->get($prefix.'/maps/'.$map->slug)
        ->assertOk();
    $document = HTMLDocument::createFromString($response->getContent(), LIBXML_NOERROR);
    $sharedMap = $document->querySelector('body > script#rodnik-shared-map');
    $translations = $document->querySelector('body > script#rodnik-translations');

    expect($sharedMap)->not->toBeNull()
        ->and($translations)->not->toBeNull()
        ->and($document->querySelector('head #rodnik-shared-map, head #rodnik-translations'))->toBeNull();

    $mapData = json_decode($sharedMap->textContent, true, flags: JSON_THROW_ON_ERROR);
    $translationData = json_decode($translations->textContent, true, flags: JSON_THROW_ON_ERROR);

    expect($mapData['id'])->toBe($map->id)->and($mapData)->not->toHaveKey('token')
        ->and($mapData['title'])->toBe($map->title)
        ->and($mapData['url'])->toBe(url($prefix.'/maps/'.$map->slug).'/')
        ->and($translationData['locale'])->toBe($locale)
        ->and($translationData['publicBaseUrl'])->toBe(url($prefix ?: '/'))
        ->and($translationData['mapTranslations']['upload_track'])->toBe(trans('ui.map.upload_track', locale: $locale));

    $home = $this->get($prefix ?: '/')->assertOk();
    $homeDocument = HTMLDocument::createFromString($home->getContent(), LIBXML_NOERROR);

    expect(json_decode($homeDocument->querySelector('body > script#rodnik-shared-map')->textContent, true, flags: JSON_THROW_ON_ERROR))
        ->toBeNull();
})->with([
    'English' => ['en', ''],
    'Russian' => ['ru', '/ru'],
]);

test('a shared spring opens its panel and highlight without recentering or redirecting', function () {
    $spring = Spring::factory()->create();
    $state = Map::factory()->make()->state;
    $state['page']['spring'] = $spring->id;
    $state['center'] = [12.3456789, 45.9876543];
    $state['zoom'] = 7.25;

    Livewire::test(Duo::class, ['sharedState' => $state])
        ->assertSet('page.spring', $spring->id)
        ->assertSet('isSharedMap', true)
        ->assertSee('data-rodnik-page', false)
        ->assertViewHas('coordinates', [(float) $spring->longitude, (float) $spring->latitude])
        ->assertDontSee('window.rodnikMap.locate(', false)
        ->assertNoRedirect();

    $target = Spring::factory()->create();
    $spring->redirect_to_spring_id = $target->id;
    $spring->save();
    Livewire::test(Duo::class, ['sharedState' => $state])
        ->assertSet('page.spring', $spring->id)
        ->assertNoRedirect();
});

test('an unavailable saved user or spring does not make the map fail to render', function () {
    $state = Map::factory()->make()->state;
    $state['page'] = ['spring' => 987654321, 'user' => 987654321, 'location' => null];

    Livewire::test(Duo::class, ['sharedState' => $state])
        ->assertSet('page.spring', null)
        ->assertSet('page.user', null)
        ->assertOk();
});

test('shared pages and my maps expose localized controls in both languages', function (string $prefix, string $share, string $myMaps, string $reportsFilter, string $editTitle) {
    $user = User::factory()->create();
    $map = Map::factory()->for($user)->create(['title' => 'My ridge route']);

    $this->actingAs($user)->get($prefix.'/maps/'.$map->slug)
        ->assertOk()
        ->assertSee($share)
        ->assertSee($myMaps)
        ->assertSee($reportsFilter)
        ->assertDontSee('aria-controls="map-edit-'.$map->slug.'"', false)
        ->assertDontSee('Only confirmed good water')
        ->assertDontSee('Только с подтверждённо хорошей водой')
        ->assertSee('href="'.url($prefix.'/user/maps').'"', false);

    $response = $this->get($prefix.'/user/maps')
        ->assertOk()
        ->assertSee($myMaps)
        ->assertSee($map->title)
        ->assertSee($editTitle)
        ->assertSee(trans('ui.maps.delete_help', locale: $prefix === '/ru' ? 'ru' : 'en'))
        ->assertDontSee('Copies saved by others will remain.')
        ->assertDontSee('Копии, сохранённые другими пользователями, останутся.')
        ->assertSee(':href="url"', false);
    $document = HTMLDocument::createFromString($response->getContent(), LIBXML_NOERROR);
    $config = $document->querySelector('main[x-data]')->getAttribute('x-data');
    expect(preg_match("/^mapLibrary\\(JSON\\.parse\\('(.*)'\\)\\)$/s", $config, $match))->toBe(1);
    $data = json_decode(json_decode('"'.$match[1].'"', flags: JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
    expect($data['items'][0]['url'])->toBe(url($prefix.'/maps/'.$map->slug).'/')
        ->and($data['items'][0]['slug'])->toBe($map->slug)
        ->and($data['endpoint'])->toBe(url($prefix.'/user/maps/options'));

    $locale = $prefix === '/ru' ? 'ru' : 'en';
    expect($document->querySelector('#map-library-help'))->toBeNull()
        ->and($document->querySelector('[aria-label="'.trans('ui.maps.library_navigation', locale: $locale).'"]'))->not->toBeNull()
        ->and($document->querySelector('a[href="'.url($prefix.'/user/tracks').'"]'))->not->toBeNull();
})->with([
    'English' => ['', 'Share', 'My maps', 'Only with reports', 'Edit'],
    'Russian' => ['/ru', 'Поделиться', 'Мои карты', 'Только с отчётами', 'Изменить'],
]);

test('map controls separate current links and saved maps with adjacent copy actions', function (string $locale, string $prefix, string $viewer) {
    $owner = User::factory()->create();
    $map = Map::factory()->for($owner)->create();
    if ($viewer !== 'guest') {
        $this->actingAs($viewer === 'owner' ? $owner : User::factory()->create());
    }

    $response = $this->get($prefix.'/maps/'.$map->slug.'/')->assertOk()
        ->assertViewHas('sharedMap', fn (array $data): bool => $data['can_update'] === ($viewer === 'owner'));
    $document = HTMLDocument::createFromString($response->getContent(), LIBXML_NOERROR);
    $triggers = $document->querySelectorAll('button[aria-controls="share-map-dialog"]');
    expect($triggers)->toHaveCount(1);
    $trigger = $triggers->item(0);
    expect($trigger->getAttribute('@click'))->toBe('open()')
        ->and($trigger->getAttribute('aria-haspopup'))->toBe('dialog')
        ->and($trigger->getAttribute(':aria-expanded'))->toBe('opened')
        ->and($trigger->textContent)->toContain(trans('ui.maps.share', locale: $locale))
        ->and($trigger->querySelector('[x-show="dirty"].sr-only')->textContent)->toBe(trans('ui.maps.unsaved', locale: $locale));

    $template = $trigger->parentElement->querySelector('template');
    $dialog = HTMLDocument::createFromString($template->innerHTML, LIBXML_NOERROR)->querySelector('#share-map-dialog');
    expect($dialog->getAttribute('aria-labelledby'))->toBe('share-map-heading')
        ->and($dialog->getAttribute(':aria-describedby'))->toBe("confirmation ? 'share-map-confirmation' : saving ? null : 'share-map-description'")
        ->and($dialog->querySelector('#share-map-heading')->textContent)->toBe(trans('ui.maps.share_title', locale: $locale))
        ->and($dialog->querySelector('#share-map-heading')->getAttribute('x-text'))->toBe("confirmation === 'created' ? ".Js::from(trans('ui.maps.confirmation_saved_title', locale: $locale))." : confirmation === 'updated' ? ".Js::from(trans('ui.maps.confirmation_updated_title', locale: $locale)).' : saving ? '.Js::from(trans('ui.maps.create_map', locale: $locale)).' : '.Js::from(trans('ui.maps.share_title', locale: $locale)))
        ->and($dialog->querySelector('#share-map-description')->getAttribute('x-show'))->toBe('!saving && !confirmation')
        ->and($dialog->querySelector('#share-map-description')->hasAttribute('x-text'))->toBeFalse()
        ->and($dialog->querySelector('#share-map-description')->textContent)->toBe(trans('ui.maps.share_view_help', locale: $locale));

    $input = $dialog->querySelector('#shared-map-link');
    $copy = $input->parentElement->querySelector('button');
    $savedInput = $dialog->querySelector('#saved-map-link');
    foreach ([$input, $savedInput] as $linkInput) {
        expect($linkInput->parentElement->getAttribute('class'))->toContain('focus-within:border-blue-600', 'focus-within:ring-1', 'focus-within:ring-blue-600')
            ->and($linkInput->parentElement->getAttribute('class'))->not->toContain('focus-within:ring-2', '/15')
            ->and($linkInput->parentElement->querySelector('button')->classList->contains('focus-visible:outline-none'))->toBeTrue();
    }
    expect($input->getAttribute(':value'))->toBe('linkUrl')
        ->and($input->hasAttribute('readonly'))->toBeTrue()
        ->and($input->getAttribute('dir'))->toBe('ltr')
        ->and($input->getAttribute('@focus'))->toBe('$el.setSelectionRange(0, 0); $el.scrollLeft = 0')
        ->and($input->getAttribute('@click'))->toBe('$el.setSelectionRange(0, $el.value.length, \'backward\'); $el.scrollLeft = 0')
        ->and($input->getAttribute('aria-label'))->toBe(trans('ui.maps.link', locale: $locale))
        ->and($input->hasAttribute('aria-describedby'))->toBeFalse()
        ->and($dialog->querySelector('label[for="shared-map-link"]'))->toBeNull()
        ->and($copy->getAttribute('@click'))->toBe('copy()')
        ->and($copy->getAttribute(':disabled'))->toBe('busy || !linkUrl')
        ->and($copy->classList->contains('bg-blue-600'))->toBeTrue()
        ->and($copy->querySelector('svg[x-show="!copied"]'))->not->toBeNull()
        ->and($copy->querySelector('svg[x-show="copied"]')->hasAttribute('x-cloak'))->toBeTrue()
        ->and($copy->querySelector('span[x-text]')->getAttribute('x-text'))->toBe('copied ? '.Js::from(trans('ui.maps.copied', locale: $locale)).' : '.Js::from(trans('ui.maps.copy', locale: $locale)))
        ->and($copy->querySelector('span[x-text]')->textContent)->toBe(trans('ui.maps.copy', locale: $locale))
        ->and($savedInput->getAttribute(':value'))->toBe('savedUrl()')
        ->and($savedInput->getAttribute('@focus'))->toBe($input->getAttribute('@focus'))
        ->and($savedInput->getAttribute('@click'))->toBe($input->getAttribute('@click'))
        ->and($savedInput->parentElement->querySelector('button')->getAttribute('@click'))->toBe('copy(true)')
        ->and($savedInput->parentElement->querySelector('button')->getAttribute(':class'))->toBe("confirmation ? 'map-button-primary focus-visible:bg-blue-700' : 'map-button-secondary focus-visible:bg-zinc-100'")
        ->and($dialog->querySelector('[data-share-current-view]')->getAttribute('x-show'))->toBe('!saving && !confirmation')
        ->and($dialog->querySelector('[data-share-saved-view]')->getAttribute('x-show'))->toBe('!saving && savedUrl()')
        ->and($dialog->querySelector('[data-share-saved-view]')->getAttribute(':class'))->toBe("confirmation ? '' : 'border-t border-zinc-100 pt-5'")
        ->and($dialog->querySelector('label[for="saved-map-link"]')->getAttribute('x-show'))->toBe('!confirmation')
        ->and($dialog->querySelector('[data-share-saved-view] p')->getAttribute('x-show'))->toBe('!confirmation && canUpdate() && dirty')
        ->and($dialog->querySelector('#shared-map-access'))->toBeNull()
        ->and(mb_trim($dialog->querySelector('[data-share-divider]')->textContent))->toBeEmpty()
        ->and($dialog->querySelector('[data-share-divider]')->classList->contains('border-t'))->toBeTrue()
        ->and($dialog->querySelector('[data-share-divider]')->getAttribute('aria-hidden'))->toBe('true')
        ->and($dialog->querySelector('[data-share-divider]')->getAttribute('x-show'))->toBe('!saving && !confirmation && !savedUrl()')
        ->and($dialog->querySelector('[x-show="trackMissing()"]')->textContent)->toContain(trans('ui.maps.deleted_track_notice', locale: $locale));

    $confirmation = $dialog->querySelector('#share-map-confirmation');
    expect($confirmation->getAttribute('x-show'))->toBe('confirmation');
    foreach (['created' => 'confirmation_saved', 'updated' => 'confirmation_updated'] as $state => $translation) {
        $message = $confirmation->querySelector('span[x-show="confirmation === \''.$state.'\'"]');
        expect($message->textContent)->toBe(trans('ui.maps.'.$translation, ['maps' => trans('ui.maps.my_maps', locale: $locale)], $locale))
            ->and($message->querySelector('a')->getAttribute('href'))->toBe(url($prefix.'/user/maps'))
            ->and($message->querySelector('a')->hasAttribute('data-rodnik-navigate'))->toBeTrue();
    }

    if ($viewer === 'guest') {
        expect($dialog->querySelector('#save-map-title'))->toBeNull()
            ->and($dialog->textContent)->toContain(trans('ui.maps.guest_track_help', locale: $locale));
    } else {
        $form = $dialog->querySelector('form');
        $saveActions = $dialog->querySelector('[data-share-save-actions]');
        expect($saveActions->getAttribute('x-show'))->toBe('!saving && !confirmation')
            ->and($saveActions->classList->contains('border-t'))->toBeFalse()
            ->and($saveActions->querySelector('p')->textContent)->toBe(trans('ui.maps.keep_view_help', locale: $locale))
            ->and($saveActions->querySelector('p')->classList->contains('text-left'))->toBeTrue()
            ->and($saveActions->querySelector('p')->classList->contains('text-center'))->toBeFalse()
            ->and($saveActions->querySelector('div')->classList->contains('grid'))->toBeTrue()
            ->and($saveActions->querySelector('div')->getAttribute(':class'))->toBe("canUpdate() ? 'grid-cols-2' : 'grid-cols-1'")
            ->and($saveActions->querySelectorAll('button.map-button-secondary')->length)->toBe(1)
            ->and($saveActions->querySelectorAll('button.map-button-primary')->length)->toBe(1)
            ->and($saveActions->querySelector('button.map-button-primary')->getAttribute('x-show'))->toBe('canUpdate()')
            ->and($saveActions->querySelector('button.map-button-primary')->getAttribute(':disabled'))->toBe('busy || !dirty || trackPending()')
            ->and($saveActions->querySelector('button.map-button-secondary')->getAttribute('@click'))->toBe('beginSave()')
            ->and($saveActions->querySelectorAll('button.min-w-0.w-full')->length)->toBe(2)
            ->and($saveActions->querySelectorAll('button.flex-1')->length)->toBe(0)
            ->and($saveActions->querySelector('button:last-child')->getAttribute('@click'))->toBe('beginSave()')
            ->and($saveActions->querySelector('button:last-child')->classList->contains('w-full'))->toBeTrue()
            ->and($saveActions->querySelector('button:last-child')->hasAttribute(':class'))->toBeFalse()
            ->and($saveActions->querySelector('button:last-child span')->getAttribute('x-text'))->toBe('canUpdate() ? '.Js::from(trans('ui.maps.create_copy', locale: $locale)).' : '.Js::from(trans('ui.maps.create_map', locale: $locale)))
            ->and($saveActions->querySelector('button:last-child span')->textContent)->toBe(trans('ui.maps.create_map', locale: $locale))
            ->and($form->getAttribute('x-show'))->toBe('saving')
            ->and($form->textContent)->not->toContain(trans('ui.maps.create_details_help', locale: $locale))
            ->and($form->getAttribute('@submit.prevent'))->toBe('save()')
            ->and($form->hasAttribute('novalidate'))->toBeTrue()
            ->and($form->querySelector('#save-map-title')->getAttribute('x-model'))->toBe('draftTitle')
            ->and($form->querySelector('#save-map-title')->hasAttribute('required'))->toBeTrue()
            ->and($form->querySelector('#save-map-title')->getAttribute('@input'))->toContain('fieldErrors.title')
            ->and($form->querySelector('#save-map-slug')->getAttribute('x-model'))->toBe('draftSlug')
            ->and($form->querySelector('#save-map-slug')->getAttribute('@input'))->toBe('slugInput()')
            ->and($form->querySelector('button[type="submit"]')->getAttribute(':disabled'))->toBe("busy || trackPending() || slugStatus !== 'valid'")
            ->and($form->querySelector('button[type="submit"]')->classList->contains('map-button-primary'))->toBeTrue()
            ->and(mb_trim($form->querySelector('button[type="submit"]')->textContent))->toBe(trans('ui.maps.create_map', locale: $locale))
            ->and($form->querySelector('button.map-button-secondary')->getAttribute('@click'))->toBe('cancelSave()')
            ->and($form->querySelector('[x-show="customLink"]'))->toBeNull()
            ->and($form->querySelector('[x-show="slugStatus === \'valid\'"]')->classList->contains('text-[#198754]'))->toBeTrue()
            ->and($form->querySelector('#save-map-slug-status')->getAttribute('x-show'))->toBe("(slugStatus === 'invalid' || slugStatus === 'failed') && slugNotice && !fieldErrors.slug")
            ->and($document->querySelector('a[href="'.url($prefix.'/user/maps').'"]')->textContent)->toContain(trans('ui.maps.my_maps_and_tracks', locale: $locale))
            ->and($dialog->querySelector('button[x-show="canUpdate()"]')->getAttribute('@click'))->toBe('update()');

        foreach (['title' => 'title_example', 'slug' => 'link_example'] as $field => $translation) {
            $exampleId = 'save-map-'.$field.'-example';
            $example = $form->querySelector('#'.$exampleId);
            expect($form->querySelector('#save-map-'.$field)->getAttribute('aria-describedby'))->toContain($exampleId)
                ->and($example->textContent)->toBe(trans('ui.maps.'.$translation, locale: $locale))
                ->and($example->classList->contains('text-zinc-500'))->toBeTrue()
                ->and($example->previousElementSibling->getAttribute('for'))->toBe('save-map-'.$field);
        }
    }
    expect($dialog->querySelector('[data-map-i18n="map.remove_track"]'))->toBeNull();
})->with([
    'English' => ['en', ''],
    'Russian' => ['ru', '/ru'],
])->with(['owner', 'guest', 'other user']);

test('map upload menus offer localized track removal alongside uploading another file', function (string $locale, string $prefix, bool $shared) {
    $path = $shared ? '/maps/'.Map::factory()->create()->slug.'/' : '/';
    $response = $this->followingRedirects()->get($prefix.$path)->assertOk();
    $document = HTMLDocument::createFromString($response->getContent(), LIBXML_NOERROR);
    $menu = $document->querySelector('[x-show="gpxTrackMenuOpen"]');
    $removeLabel = $menu->querySelector('[data-map-i18n="map.remove_track"]');
    $removeButton = $removeLabel->parentElement;

    expect($menu->hasAttribute('x-cloak'))->toBeTrue()
        ->and($removeButton->previousElementSibling->textContent)->toBe(trans('ui.map.upload_new_track_or_photo', locale: $locale))
        ->and($removeLabel->textContent)->toBe(trans('ui.map.remove_track', locale: $locale))
        ->and($removeButton->tagName)->toBe('BUTTON')
        ->and($removeButton->getAttribute('type'))->toBe('button')
        ->and($removeButton->getAttribute('@click'))->toBe('window.rodnikMap.trackLayer.clear(); gpxTrackMenuOpen = false; forceUpload = false')
        ->and($menu->querySelector('[data-map-i18n="map.upload_new_track_or_photo"]')->textContent)
        ->toBe(trans('ui.map.upload_new_track_or_photo', locale: $locale));
})->with(['English' => ['en', ''], 'Russian' => ['ru', '/ru']])
    ->with(['main map' => false, 'shared map' => true]);

test('guest shared links offer copying but require signing in to save', function () {
    $map = Map::factory()->create();

    $this->get(route('maps.show', $map))
        ->assertOk()
        ->assertSee('Share')
        ->assertSee('Copy link')
        ->assertDontSee('Save as a new map')
        ->assertDontSee('x-model="draftTitle"', false)
        ->assertDontSee('id="shared-map-details-description"', false)
        ->assertDontSee('id="shared-map-details-slug"', false)
        ->assertDontSee('Create link')
        ->assertSee('Sign in to save maps.')
        ->assertSee('Tracks uploaded as a guest cannot be deleted later.')
        ->assertViewHas('sharedMap', fn (array $shared): bool => $shared['can_update'] === false && ! isset($shared['edit_url']));
});

test('shared map payloads keep the page language in copied and login links', function (string $prefix) {
    $map = Map::factory()->create();

    $this->get($prefix.'/maps/'.$map->slug)
        ->assertOk()
        ->assertViewHas('sharedMap', fn (array $sharedMap): bool => $sharedMap['url'] === url($prefix.'/maps/'.$map->slug).'/'
            && $sharedMap['login_url'] === url($prefix.'/maps/'.$map->slug.'/login'));
})->with(['English' => '', 'Russian' => '/ru']);

test('guest login returns to the same shared map and language before saving a personal copy', function (string $prefix) {
    $map = Map::factory()->create();
    $user = User::factory()->create();
    $destination = url($prefix.'/maps/'.$map->slug).'/';

    $this->get($prefix.'/maps/'.$map->slug.'/login?redirect=https://example.com')
        ->assertRedirect(route('login'))
        ->assertSessionHas('url.intended', $destination);
    $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect($destination);
    $this->assertAuthenticatedAs($user);
    $this->get($destination)->assertOk()
        ->assertViewHas('sharedMap', fn (array $sharedMap): bool => $sharedMap['can_update'] === false);
    $this->postJson(route('maps.store'), [
        'title' => 'My copy',
        'state' => $map->state,
        'track_token' => $map->track?->token,
    ])->assertCreated()->assertJsonPath('can_update', true);

    expect($map->refresh()->user_id)->toBeNull()
        ->and($user->maps()->sole()->slug)->not->toBe($map->slug);
})->with(['English' => '', 'Russian' => '/ru']);

test('a signed in visitor follows the shared map login link directly to the map', function () {
    $map = Map::factory()->create();

    $this->actingAs(User::factory()->create())->get(route('ru.maps.login', $map))
        ->assertRedirect(route('ru.maps.show', $map).'/')
        ->assertSessionMissing('url.intended');
});

test('unknown map login links cannot set a return destination', function () {
    $this->get('/maps/missing-map/login')
        ->assertNotFound()
        ->assertSessionMissing('url.intended');
});

test('saved titles are escaped in lists and embedded map state', function () {
    $user = User::factory()->create();
    $title = '</script><script>alert("map")</script>';
    $map = Map::factory()->for($user)->create(['title' => $title]);

    $this->actingAs($user)->get(route('maps.show', $map))
        ->assertOk()
        ->assertDontSee($title, false);
    $response = $this->get(route('maps.index'))
        ->assertOk()
        ->assertViewHas('maps', fn ($maps): bool => $maps->sole()->title === $title)
        ->assertDontSee($title, false);
    $document = HTMLDocument::createFromString($response->getContent(), LIBXML_NOERROR);
    $library = $document->querySelector('main[x-data]');
    $row = HTMLDocument::createFromString($library->querySelector('template[x-for]')->innerHTML, LIBXML_NOERROR);
    $editor = HTMLDocument::createFromString($library->querySelector('template[x-if="editingRecord"]')->innerHTML, LIBXML_NOERROR);
    $form = HTMLDocument::createFromString($editor->querySelector('template[x-teleport]')->innerHTML, LIBXML_NOERROR);

    expect($library->getAttribute('x-data'))->toContain('u003C')->not->toContain('</script>')
        ->and($form->querySelector('input[x-model="draftTitle"]'))->not->toBeNull()
        ->and($form->querySelector('input[x-model="draftTitle"]')->hasAttribute('required'))->toBeTrue()
        ->and($row->querySelector('[x-text="title"]'))->not->toBeNull()
        ->and($row->querySelector('[x-html]'))->toBeNull();
});
