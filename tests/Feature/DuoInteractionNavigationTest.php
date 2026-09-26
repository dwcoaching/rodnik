<?php

declare(strict_types=1);

use App\Jobs\SendReportNotification;
use App\Jobs\SendSpringRevisionNotification;
use App\Livewire\Duo\Springs\Create as SpringLocation;
use App\Livewire\Duo\Springs\Show as SpringShow;
use App\Livewire\Reports\Create as CreateReport;
use App\Livewire\Springs\Create as EditSpring;
use App\Models\Map;
use App\Models\Report;
use App\Models\Spring;
use App\Models\SpringRevision;
use App\Models\SpringTile;
use App\Models\User;
use App\Models\WateredSpringTile;
use Dom\HTMLDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake([SendReportNotification::class, SendSpringRevisionNotification::class]);
    Storage::fake(SpringTile::DISK);
    Storage::fake(WateredSpringTile::DISK);
});

function expectDuoNavigationLinks(string $html, array $urls): void
{
    $document = HTMLDocument::createFromString($html, LIBXML_NOERROR);

    foreach ($urls as $url) {
        $links = $document->querySelectorAll('a[href="'.$url.'"]');

        expect($links->length, $url)->toBeGreaterThan(0);

        foreach ($links as $link) {
            expect($link->hasAttribute('data-rodnik-navigate'), $url)->toBeTrue();
        }
    }
}

test('creating a source navigates to its details form without a document redirect', function () {
    $this->actingAs(User::factory()->create());

    $component = Livewire::test(SpringLocation::class, ['springId' => null, 'location' => 1])
        ->set('latitude', 55.75)
        ->set('longitude', 37.62)
        ->call('create');
    $spring = Spring::query()->sole();

    $component->assertRedirect(localized_route('springs.edit', ['spring' => $spring]));

    expect($component->effects['redirectUsingNavigate'] ?? false)->toBeTrue()
        ->and((float) $spring->latitude)->toBe(55.75)
        ->and((float) $spring->longitude)->toBe(37.62);
});

test('updating source coordinates navigates back to its source panel', function () {
    $this->actingAs(User::factory()->create());
    $spring = Spring::factory()->create(['latitude' => 55.75, 'longitude' => 37.62]);

    $component = Livewire::test(SpringLocation::class, ['springId' => $spring->id, 'location' => 1])
        ->set('latitude', 55.76)
        ->set('longitude', 37.63)
        ->call('update')
        ->assertRedirect(duo_route(['spring' => $spring->id]));

    expect($component->effects['redirectUsingNavigate'] ?? false)->toBeTrue()
        ->and((float) $spring->fresh()->latitude)->toBe(55.76)
        ->and((float) $spring->fresh()->longitude)->toBe(37.63);
});

test('saving source details navigates back to the updated source', function () {
    $this->actingAs(User::factory()->create());
    $spring = Spring::factory()->create(['latitude' => 55.75, 'longitude' => 37.62]);

    $component = Livewire::test(EditSpring::class, ['springId' => $spring->id])
        ->set('name', 'Forest water')
        ->set('type', 'Spring')
        ->call('store')
        ->assertRedirect(duo_route(['spring' => $spring->id]));

    expect($component->effects['redirectUsingNavigate'] ?? false)->toBeTrue()
        ->and($spring->fresh()->name)->toBe('Forest water');
});

test('saving a report navigates back to the source with its new report', function () {
    $this->actingAs(User::factory()->create());
    $spring = Spring::factory()->create(['latitude' => 55.75, 'longitude' => 37.62]);

    $component = Livewire::test(CreateReport::class, ['springId' => $spring->id, 'reportId' => null])
        ->set('visited_at', '2020-01-01')
        ->set('state', 'running')
        ->set('quality', 'good')
        ->set('comment', 'Water is running')
        ->call('store')
        ->assertHasNoErrors()
        ->assertRedirect(duo_route(['spring' => $spring->id]));

    expect($component->effects['redirectUsingNavigate'] ?? false)->toBeTrue()
        ->and($spring->reports()->sole()->comment)->toBe('Water is running');
});

test('source administration uses Livewire navigation after a successful action', function (string $action) {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $spring = Spring::factory()->create(['latitude' => 55.75, 'longitude' => 37.62]);

    if ($action === 'unmerge') {
        $spring->redirect_to_spring_id = Spring::factory()->create(['latitude' => 55.7505, 'longitude' => 37.62])->id;
        $spring->save();
    }

    $component = Livewire::test(SpringShow::class, ['springId' => $spring->id, 'userId' => null])
        ->call($action)
        ->assertRedirect(duo_route(in_array($action, ['hide', 'annihilate'], true) ? [] : ['spring' => $spring->id]));

    expect($component->effects['redirectUsingNavigate'] ?? false)->toBeTrue();

    if ($action === 'hide') {
        expect($spring->fresh()->hidden_at)->not->toBeNull();
    } elseif ($action === 'annihilate') {
        expect($spring->fresh())->toBeNull();
    } elseif ($action === 'unmerge') {
        expect($spring->fresh()->redirect_to_spring_id)->toBeNull();
    }
})->with(['hide', 'annihilate', 'invalidateTiles', 'unmerge']);

test('source administration still rejects unauthorized navigation actions', function (string $action) {
    $this->actingAs(User::factory()->create(['is_admin' => false, 'is_superadmin' => false]));
    $spring = Spring::factory()->create(['latitude' => 55.75, 'longitude' => 37.62]);

    Livewire::test(SpringShow::class, ['springId' => $spring->id, 'userId' => null])
        ->call($action)
        ->assertForbidden();

    expect($spring->fresh())->not->toBeNull()
        ->and($spring->fresh()->hidden_at)->toBeNull();
})->with(['hide', 'annihilate', 'invalidateTiles', 'unmerge']);

test('source panel links opt in to application navigation including merged sources and editing', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $target = Spring::factory()->create(['latitude' => 55.75, 'longitude' => 37.62]);
    $spring = Spring::factory()->create(['redirect_to_spring_id' => $target->id]);
    $report = Report::factory()->for($spring)->for($user)->create(['from_osm' => null]);
    $html = $this->get(duo_route(['spring' => $spring->id, 'redirect' => 'false']))->assertOk()->getContent();

    expectDuoNavigationLinks($html, [
        duo_route(['spring' => $target->id]),
        duo_route(['spring' => $spring->id, 'location' => 1]),
        localized_route('springs.edit', ['spring' => $spring]),
        localized_route('springs.history', ['spring' => $spring]),
        localized_route('reports.create', ['spring_id' => $spring]),
        localized_route('reports.edit', ['report' => $report]),
        localized_route('maps.index'),
        route('profile.show'),
    ]);
});

test('source forms and history return to the source through application navigation', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $spring = Spring::factory()->create(['latitude' => 55.75, 'longitude' => 37.62]);
    SpringRevision::forceCreate([
        'spring_id' => $spring->id,
        'user_id' => $user->id,
        'revision_type' => 'user',
        'new_name' => 'Forest water',
    ]);

    foreach (['springs.edit', 'springs.history'] as $routeName) {
        $html = $this->get(localized_route($routeName, ['spring' => $spring]))->assertOk()->getContent();
        expectDuoNavigationLinks($html, [duo_route(['spring' => $spring->id])]);
    }

    $html = $this->get(localized_route('reports.create', ['spring_id' => $spring]))->assertOk()->getContent();
    expectDuoNavigationLinks($html, [duo_route(['spring' => $spring->id])]);
});

test('saved map links and navigation menus use application navigation', function () {
    $owner = User::factory()->create();
    $map = Map::factory()->for($owner)->create();
    $html = $this->actingAs($owner)->get(localized_route('maps.index'))->assertOk()->getContent();

    expectDuoNavigationLinks($html, [
        localized_public_path(),
        localized_route('maps.index'),
        route('profile.show'),
    ]);

    $document = HTMLDocument::createFromString($html, LIBXML_NOERROR);
    $library = $document->querySelector('#map-library');
    $row = HTMLDocument::createFromString($library->querySelector('template[x-for]')->innerHTML, LIBXML_NOERROR);
    $link = $row->querySelector('a[x-text="title"]');
    expect(preg_match("/^mapLibrary\\(JSON\\.parse\\('(.*)'\\)\\)$/s", $library->getAttribute('x-data'), $match))->toBe(1);
    $config = json_decode(json_decode('"'.$match[1].'"', flags: JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);

    expect($config['items'][0]['url'])->toBe(localized_route('maps.show', ['map' => $map->slug]).'/')
        ->and($link->getAttribute(':href'))->toBe('url')
        ->and($link->hasAttribute('data-rodnik-navigate'))->toBeTrue()
        ->and($link->hasAttribute('data-rodnik-exact-url'))->toBeTrue();
    expect($document->querySelector('form[action="'.route('logout').'"] a')->hasAttribute('data-rodnik-navigate'))->toBeFalse();
});

test('guest entry points use application navigation', function () {
    foreach ([duo_route(), duo_route(['location' => 1])] as $url) {
        $html = $this->get($url)->assertOk()->getContent();
        expectDuoNavigationLinks($html, [route('login'), route('register')]);
    }
});

test('saved map pagination appends from the private library endpoint', function () {
    $owner = User::factory()->create();
    Map::factory()->count(21)->for($owner)->create();

    $html = $this->actingAs($owner)->get(localized_route('maps.index'))->assertOk()->getContent();
    $document = HTMLDocument::createFromString($html, LIBXML_NOERROR);
    $library = $document->querySelector('#map-library');
    $more = $library->querySelector('div[x-show="nextPage"] button');
    expect(preg_match("/^mapLibrary\\(JSON\\.parse\\('(.*)'\\)\\)$/s", $library->getAttribute('x-data'), $match))->toBe(1);
    $config = json_decode(json_decode('"'.$match[1].'"', flags: JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);

    expect($config['items'])->toHaveCount(20)
        ->and($config['nextPage'])->toBe(localized_route('maps.options', ['page' => 2]))
        ->and($more->getAttribute('@click'))->toBe('load(true)')
        ->and($more->getAttribute(':disabled'))->toBe('loading');
    $this->getJson($config['nextPage'])->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('next_page_url', null);
});

test('documentation and account entry pages mark safe internal navigation only', function (string $path) {
    $spring = Spring::factory()->create(['latitude' => 55.75, 'longitude' => 37.62]);
    Report::factory()->for($spring)->create();
    Storage::fake('public');
    Storage::disk('public')->put('exports/rodnik-from-2026-01-01_12-00-00.json', '{}');

    $html = $this->get($path)->assertOk()->getContent();
    $document = HTMLDocument::createFromString($html, LIBXML_NOERROR);
    $internalLinks = 0;

    foreach ($document->querySelectorAll('a[href]') as $link) {
        $href = $link->getAttribute('href');
        $internal = str_starts_with($href, url('/').'/') || str_starts_with($href, '/');
        $download = str_starts_with($href, '/exports/') || $link->hasAttribute('download');
        $newTab = $link->getAttribute('target') === '_blank';

        if ($internal && ! $download && ! $newTab) {
            expect($link->hasAttribute('data-rodnik-navigate'), $href)->toBeTrue();
            $internalLinks++;
        } else {
            expect($link->hasAttribute('data-rodnik-navigate'), $href)->toBeFalse();
        }
    }

    expect($internalLinks)->toBeGreaterThan(0);

    foreach ($document->querySelectorAll('form[method="POST"], form[method="post"]') as $form) {
        expect($form->hasAttribute('data-rodnik-navigate'))->toBeFalse();
    }
})->with([
    'login' => '/login',
    'registration' => '/register',
    'password reset' => '/forgot-password',
    'about' => '/docs/about',
    'privacy' => '/docs/privacy',
    'deletion instructions' => '/docs/delete-account',
    'exports' => '/docs/exports',
    'contributors' => '/docs/users',
    'admin overview' => '/docs/admin',
    'duplicate candidates' => '/docs/admin/duplicates',
    'source scores' => '/docs/admin/spring-scores',
]);

test('statistics pages include the application navigation runtime for direct visits', function (string $path) {
    $html = $this->get($path)->assertOk()->getContent();
    $document = HTMLDocument::createFromString($html, LIBXML_NOERROR);

    expect($document->querySelector('script[type="module"][src]'))->not->toBeNull()
        ->and($document->querySelector('meta[name="csrf-token"]'))->not->toBeNull()
        ->and($document->querySelector('#rodnik-translations'))->not->toBeNull()
        ->and($html)->toContain('window.livewireScriptConfig');

    foreach ($document->querySelectorAll('a[href]') as $link) {
        expect($link->hasAttribute('data-rodnik-navigate'))->toBeTrue();
    }
})->with(['/moscow-stats', '/moscow-stats?area=mkad']);
