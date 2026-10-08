<?php

declare(strict_types=1);

use App\Models\Map;
use App\Models\Track;
use App\Models\User;
use App\Support\Docs\PublicMaps;
use App\Support\Docs\RoutePreview;
use App\Support\DuoUrl;
use Dom\HTMLDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;

uses(RefreshDatabase::class);

test('the public map list shows every saved map, most viewed and most recently updated first', function () {
    $owner = User::factory()->create(['name' => 'Map owner']);
    $author = User::factory()->create(['name' => 'Another author']);
    $track = Track::factory()->create(['name' => 'Coastal path', 'user_id' => $author->id]);
    $older = Map::factory()->for($owner)->create(['title' => 'Older favourite', 'views_count' => 9, 'updated_at' => now()->subDays(3)]);
    $newer = Map::factory()->for($author)->create(['title' => 'Newer favourite', 'views_count' => 9, 'updated_at' => now()->subDay(), 'track_id' => $track->id]);
    $first = Map::factory()->for($owner)->create(['title' => 'Unviewed first', 'views_count' => 0, 'updated_at' => now()->subWeek()]);
    $second = Map::factory()->create(['title' => 'Unviewed without author', 'views_count' => 0, 'updated_at' => $first->updated_at]);
    $top = Map::factory()->for($author)->create(['title' => 'Most viewed', 'views_count' => 120, 'updated_at' => now()->subMonth()]);

    $response = $this->get('/docs/maps')
        ->assertOk()
        ->assertSee('<html lang="en">', false)
        ->assertSee('5 saved maps')
        ->assertSeeInOrder(['Most viewed', 'Newer favourite', 'Older favourite', 'Unviewed first', 'Unviewed without author'])
        ->assertDontSee('rel="next"', false)
        ->assertViewHas('maps', function (LengthAwarePaginator $maps) use ($top, $newer, $older, $first, $second): bool {
            expect($maps->getCollection()->modelKeys())->toBe([$top->id, $newer->id, $older->id, $first->id, $second->id])
                ->and($maps->total())->toBe(5)
                ->and($maps->hasPages())->toBeFalse();

            return true;
        });

    $document = HTMLDocument::createFromString($response->getContent(), LIBXML_NOERROR);
    $rows = $document->querySelectorAll('main ol > li');
    $newerRow = $rows->item(1);
    $authorless = $rows->item(4);

    expect($document->querySelector('h1')->textContent)->toBe('Saved maps')
        ->and($rows->length)->toBe(5)
        ->and($newerRow->querySelector('h2 a')->getAttribute('href'))->toBe($newer->publicData(null)['url'])
        ->and($newerRow->querySelector('h2 a')->hasAttribute('data-rodnik-navigate'))->toBeTrue()
        ->and($newerRow->querySelector('p a')->getAttribute('href'))->toBe(app(DuoUrl::class)->absolute(['user' => $author->id], 'en'))
        ->and($newerRow->querySelector('p a')->textContent)->toBe('Another author')
        ->and($newerRow->querySelector('time')->getAttribute('datetime'))->toBe($newer->updated_at->toIso8601String())
        ->and($newerRow->querySelector('time')->textContent)->toBe($newer->updated_at->locale('en')->isoFormat('ll'))
        ->and($newerRow->textContent)->toContain('Track: Coastal path')
        ->and($newerRow->querySelector('path[data-route-preview]')->getAttribute('d'))->toBe(RoutePreview::path($track->summary['preview'], 50))
        ->and($authorless->querySelectorAll('a')->length)->toBe(1)
        ->and($authorless->querySelector('path[data-route-preview]'))->toBeNull()
        ->and($authorless->textContent)->toContain('55.76° N, 37.62° E');
});

test('the public map list is localized', function () {
    $author = User::factory()->create(['name' => 'Автор карты']);
    $track = Track::factory()->create(['name' => 'Горная тропа']);
    $map = Map::factory()->for($author)->create(['title' => 'Карта перевала', 'track_id' => $track->id]);

    $this->get('/ru/docs/maps')
        ->assertOk()
        ->assertSee('<html lang="ru">', false)
        ->assertSee('Сохранённые карты')
        ->assertSee('1 сохранённая карта')
        ->assertSee('Трек: Горная тропа')
        ->assertSee($map->updated_at->locale('ru')->isoFormat('ll'))
        ->assertSee('href="'.url('/ru/maps/'.$map->slug).'/"', false)
        ->assertSee('href="'.app(DuoUrl::class)->absolute(['user' => $author->id], 'ru').'"', false)
        ->assertSee('href="/ru/docs" data-rodnik-navigate', false);
});

test('the public map list is indexable with localized canonical and alternate links', function (string $prefix, string $title) {
    Map::factory()->create();

    $this->get($prefix.'/docs/maps')
        ->assertOk()
        ->assertSee('<title>'.$title.'</title>', false)
        ->assertSee('<link rel="canonical" href="'.url($prefix.'/docs/maps').'">', false)
        ->assertSee('<link rel="alternate" hreflang="en" href="'.url('/docs/maps').'">', false)
        ->assertSee('<link rel="alternate" hreflang="ru" href="'.url('/ru/docs/maps').'">', false)
        ->assertSee('<link rel="alternate" hreflang="x-default" href="'.url('/docs/maps').'">', false)
        ->assertDontSee('noindex', false);

    $this->get($prefix.'/docs/maps?page=1&utm_source=newsletter')
        ->assertOk()
        ->assertSee('<link rel="canonical" href="'.url($prefix.'/docs/maps').'">', false);
})->with([
    'English' => ['', 'Saved maps of water sources and routes — Rodnik.today'],
    'Russian' => ['/ru', 'Сохранённые карты источников воды и маршрутов — Rodnik.today'],
]);

test('the public map list has an empty state', function (string $prefix, string $message) {
    $this->get($prefix.'/docs/maps')->assertOk()->assertSee($message)->assertDontSee('<ol', false);
})->with([
    'English' => ['', 'No saved maps yet.'],
    'Russian' => ['/ru', 'Сохранённых карт пока нет.'],
]);

test('the public map list stays on one page below the limit and rejects other pages', function () {
    Map::factory()->count(3)->create();

    $this->get('/docs/maps')->assertOk()->assertDontSee('data-rodnik-pagination', false);
    $this->get('/docs/maps?page=2')->assertNotFound();
});

test('the public map list is a single page below a thousand maps and pages by a thousand above', function () {
    $maps = new PublicMaps;

    expect(PublicMaps::SINGLE_PAGE_LIMIT)->toBe(1000)
        ->and(PublicMaps::PER_PAGE)->toBe(1000)
        ->and($maps->perPage(0))->toBe(1)
        ->and($maps->perPage(68))->toBe(68)
        ->and($maps->perPage(999))->toBe(999)
        ->and($maps->perPage(1000))->toBe(1000)
        ->and($maps->perPage(12000))->toBe(1000);
});

test('the public map list paginates with prev and next links above the limit', function () {
    app()->instance(PublicMaps::class, new PublicMaps(singlePageLimit: 3, perPage: 2));
    Map::factory()->count(3)->sequence(fn ($sequence): array => [
        'title' => 'Paged map '.$sequence->index,
        'views_count' => 10 - $sequence->index,
    ])->create();

    $this->get('/docs/maps')
        ->assertOk()
        ->assertSee(['Paged map 0', 'Paged map 1', '3 saved maps'])
        ->assertDontSee('Paged map 2')
        ->assertSee('rel="next"', false)
        ->assertSee('href="'.url('/docs/maps?page=2').'"', false)
        ->assertSee('<link rel="canonical" href="'.url('/docs/maps').'">', false);

    $this->get('/ru/docs/maps?page=2')
        ->assertOk()
        ->assertSee('Paged map 2')
        ->assertDontSee('Paged map 0')
        ->assertSee('rel="prev"', false)
        ->assertSee('<link rel="canonical" href="'.url('/ru/docs/maps?page=2').'">', false)
        ->assertSee('<link rel="alternate" hreflang="en" href="'.url('/docs/maps?page=2').'">', false);

    $this->get('/docs/maps?page=3')->assertNotFound();
});

test('the privacy policy explains that saved maps are public', function (string $prefix, array $texts, string $previous) {
    $this->get($prefix.'/docs/privacy')
        ->assertOk()
        ->assertSee($texts)
        ->assertDontSee($previous);
})->with([
    'English' => ['', [
        'Saved maps are public: each one is listed on the public maps page with its title, author name, map view and attached track, can be found through site search and may be indexed by search engines.',
        'A track that is not attached to a saved map stays accessible only to people who have its link.',
        'deleting a saved map removes it from the public list',
    ], 'Anyone with the map or track link can view them.'],
    'Russian' => ['/ru', [
        'Сохранённые карты общедоступны: каждая из них попадает в публичный список карт вместе с названием, именем автора, видом карты и прикреплённым треком, её можно найти через поиск по сайту, и она может индексироваться поисковыми системами.',
        'Трек, не прикреплённый к сохранённой карте, доступен только тем, у кого есть ссылка на него.',
        'после удаления сохранённой карты она исчезает из публичного списка',
    ], 'Они доступны любому, у кого есть ссылка на карту или трек.'],
]);

test('public map lists only name and draw tracks uploaded by the map author or a guest', function () {
    $author = User::factory()->create(['name' => 'Author']);
    $other = User::factory()->create(['name' => 'Other']);
    $own = Map::factory()->for($author)->create(['title' => 'Own track map', 'views_count' => 3,
        'track_id' => Track::factory()->create(['name' => 'Author path', 'user_id' => $author->id])->id]);
    $guest = Map::factory()->for($author)->create(['title' => 'Guest track map', 'views_count' => 2,
        'track_id' => Track::factory()->create(['name' => 'Guest path', 'user_id' => null])->id]);
    $borrowed = Map::factory()->for($author)->create(['title' => 'Borrowed track map', 'views_count' => 1,
        'track_id' => Track::factory()->create(['name' => 'Private path of another user', 'user_id' => $other->id])->id]);

    $document = HTMLDocument::createFromString($this->get('/docs/maps')->assertOk()->getContent(), LIBXML_NOERROR);
    $rows = $document->querySelectorAll('main ol > li');

    expect($rows->item(0)->textContent)->toContain('Track: Author path')
        ->and($rows->item(0)->querySelector('path[data-route-preview]'))->not->toBeNull()
        ->and($rows->item(1)->textContent)->toContain('Track: Guest path')
        ->and($rows->item(2)->textContent)->not->toContain('Private path of another user')
        ->and($rows->item(2)->querySelector('path[data-route-preview]'))->toBeNull()
        ->and(PublicMaps::listsTrack($borrowed->load('track')))->toBeFalse()
        ->and(PublicMaps::listsTrack($own->load('track')))->toBeTrue()
        ->and(PublicMaps::listsTrack($guest->load('track')))->toBeTrue();
    $this->get('/docs')->assertOk()->assertDontSee('Private path of another user');
});

test('the back link of the public map list is a separately named landmark', function () {
    $document = HTMLDocument::createFromString($this->get('/docs/maps')->assertOk()->getContent(), LIBXML_NOERROR);

    expect($document->querySelector('main nav')->getAttribute('aria-label'))->toBe('Breadcrumbs');
});
