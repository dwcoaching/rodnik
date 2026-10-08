<?php

declare(strict_types=1);

use App\Models\City;
use App\Models\Country;
use App\Models\Map;
use App\Models\Region;
use App\Models\Report;
use App\Models\Spring;
use App\Models\Track;
use App\Models\User;
use App\Support\Docs\DocsOverview;
use App\Support\Docs\RoutePreview;
use App\Support\DuoUrl;
use Dom\HTMLDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

/** @return array{country: Country, region: Region, city: City, leader: User, runnerUp: User, track: Track, popular: Map, quiet: Map} */
function docsOverviewWorld(): array
{
    $country = Country::create(['code' => 'RU', 'name' => 'Russia', 'name_ru' => 'Россия', 'slug' => 'russia-ru', 'is_active' => true]);
    $region = Region::create(['source_key' => 'geonames-admin1:RU.47', 'source_country_code' => 'RU', 'admin1_code' => '47', 'country_id' => $country->id, 'name' => 'Moscow Oblast', 'name_ru' => 'Московская область', 'slug' => 'moscow-oblast-524925', 'is_active' => true]);
    $city = City::create(['source_key' => 'geonames:524901', 'source_country_code' => 'RU', 'geonames_id' => 524901, 'country_id' => $country->id, 'region_id' => $region->id, 'name' => 'Moscow', 'name_ru' => 'Москва', 'slug' => 'moscow-524901', 'latitude' => 55.75, 'longitude' => 37.62, 'is_active' => true]);
    $spring = Spring::withoutEvents(fn (): Spring => Spring::factory()->create([
        'country_id' => $country->id,
        'region_id' => $region->id,
        'nearest_city_id' => $city->id,
        'latitude' => 55.75,
        'longitude' => 37.62,
    ]));
    $leader = User::factory()->create(['name' => 'Leading explorer']);
    $runnerUp = User::factory()->create(['name' => 'Second explorer']);
    Report::factory()->count(2)->for($leader)->for($spring)->create();
    Report::factory()->for($runnerUp)->for($spring)->create();
    $track = Track::factory()->create(['name' => 'Ridge traverse', 'user_id' => $leader->id]);
    $popular = Map::factory()->for($leader)->create(['title' => 'Popular ridge map', 'track_id' => $track->id, 'views_count' => 50]);
    $quiet = Map::factory()->for($runnerUp)->create(['title' => 'Quiet valley map', 'views_count' => 2]);

    return compact('country', 'region', 'city', 'leader', 'runnerUp', 'track', 'popular', 'quiet');
}

function docsDocument(TestResponse $response): HTMLDocument
{
    return HTMLDocument::createFromString($response->getContent(), LIBXML_NOERROR);
}

/** @return list<string> */
function docsTexts(HTMLDocument $document, string $selector): array
{
    $texts = [];

    foreach ($document->querySelectorAll($selector) as $node) {
        $texts[] = mb_trim(preg_replace('/\s+/u', ' ', $node->textContent));
    }

    return $texts;
}

/** @return list<string> */
function docsHrefs(HTMLDocument $document, string $selector): array
{
    $hrefs = [];

    foreach ($document->querySelectorAll($selector) as $node) {
        $hrefs[] = $node->getAttribute('href');
    }

    return $hrefs;
}

test('the docs overview presents stats, contributors, maps, open data, locations and help', function (string $locale, string $prefix, array $copy) {
    $world = docsOverviewWorld();

    $response = $this->get($prefix.'/docs')
        ->assertOk()
        ->assertSee('<html lang="'.$locale.'">', false)
        ->assertSee('<title>'.$copy['title'].'</title>', false)
        ->assertSeeInOrder($copy['sections']);
    $document = docsDocument($response);

    expect(docsTexts($document, 'h1'))->toBe([$copy['heading']])
        ->and(docsTexts($document, 'dl dd'))->toBe(['1', '3', '2', '2', '1'])
        ->and(docsTexts($document, 'dl dt'))->toBe($copy['stats'])
        ->and(docsHrefs($document, 'section[aria-labelledby="docs-contributors"] ol a'))->toBe([
            app(DuoUrl::class)->absolute(['user' => $world['leader']->id], $locale),
            app(DuoUrl::class)->absolute(['user' => $world['runnerUp']->id], $locale),
        ])
        ->and(docsTexts($document, 'section[aria-labelledby="docs-contributors"] ol li'))->toBe($copy['contributors'])
        ->and(docsHrefs($document, 'section[aria-labelledby="docs-maps"] article h3 a'))->toBe([
            $world['popular']->publicData(null, $locale)['url'],
            $world['quiet']->publicData(null, $locale)['url'],
        ])
        ->and($document->querySelector('section[aria-labelledby="docs-maps"] path[data-route-preview]')?->getAttribute('d'))
        ->toBe(RoutePreview::path($world['track']->summary['preview'], 50))
        ->and(docsTexts($document, 'section[aria-labelledby="docs-maps"] article'))->toBe(str_replace(':date', $world['popular']->updated_at->locale($locale)->isoFormat('ll'), $copy['maps']))
        ->and(docsHrefs($document, 'section[aria-labelledby="docs-locations"] ol a'))->toBe([
            url($prefix.'/docs/countries/russia-ru'),
            url($prefix.'/docs/regions/moscow-oblast-524925'),
            url($prefix.'/docs/cities/moscow-524901'),
        ])
        ->and(docsTexts($document, 'section[aria-labelledby="docs-locations"] ol a'))->toBe($copy['places']);

    $response
        ->assertSee('href="'.$prefix.'/docs/maps"', false)
        ->assertSee($copy['all_maps'])
        ->assertSee('href="'.url($prefix.'/docs/users').'"', false)
        ->assertSee($copy['all_contributors'])
        ->assertSee('href="'.url($prefix.'/docs/countries').'"', false)
        ->assertSee($copy['all_countries'])
        ->assertSee('href="'.$prefix.'/docs/exports"', false)
        ->assertSee('href="'.$prefix.'/docs/legend"', false)
        ->assertSee('href="'.$prefix.'/docs/about"', false)
        ->assertSee('href="'.$prefix.'/docs/contact-us"', false)
        ->assertSee('href="'.url($prefix.'/docs/privacy').'"', false)
        ->assertSee('href="'.url($prefix.'/docs/delete-account').'"', false);
})->with([
    'English' => ['en', '', [
        'title' => 'Rodnik.today overview: water sources, maps and open data',
        'heading' => 'Rodnik.today overview',
        'sections' => ['Spring explorers', 'Saved maps', 'Open data', 'Water sources by location', 'About and help'],
        'stats' => ['water source', 'reports', 'contributors', 'saved maps', 'country with water sources'],
        'contributors' => ['1 Leading explorer 1 unique water source 2 reports', '2 Second explorer 1 unique water source 1 report'],
        'maps' => ['Popular ridge map Leading explorer · :date Track: Ridge traverse', 'Quiet valley map Second explorer · :date 55.76° N, 37.62° E'],
        'places' => ['Russia 1 water source', 'Moscow Oblast · Russia 1 water source', 'Moscow · Russia 1 water source'],
        'all_maps' => 'All maps (2)',
        'all_contributors' => 'All contributors (2)',
        'all_countries' => 'All countries (1)',
    ]],
    'Russian' => ['ru', '/ru', [
        'title' => 'Обзор Rodnik.today: источники воды, карты и открытые данные',
        'heading' => 'Обзор Rodnik.today',
        'sections' => ['Родникологи', 'Сохранённые карты', 'Открытые данные', 'Источники воды по местоположению', 'О проекте и помощь'],
        'stats' => ['источник воды', 'отчёта', 'участника', 'сохранённые карты', 'страна с источниками'],
        'contributors' => ['1 Leading explorer 1 уникальный источник 2 отчёта', '2 Second explorer 1 уникальный источник 1 отчёт'],
        'maps' => ['Popular ridge map Leading explorer · :date Трек: Ridge traverse', 'Quiet valley map Second explorer · :date 55.76° N, 37.62° E'],
        'places' => ['Россия 1 источник воды', 'Московская область · Россия 1 источник воды', 'Москва · Россия 1 источник воды'],
        'all_maps' => 'Все карты (2)',
        'all_contributors' => 'Все участники (2)',
        'all_countries' => 'Все страны (1)',
    ]],
]);

test('the docs overview is indexable with localized canonical and alternate links', function (string $prefix) {
    $this->get($prefix.'/docs')
        ->assertOk()
        ->assertSee('<link rel="canonical" href="'.url($prefix.'/docs').'">', false)
        ->assertSee('<link rel="alternate" hreflang="en" href="'.url('/docs').'">', false)
        ->assertSee('<link rel="alternate" hreflang="ru" href="'.url('/ru/docs').'">', false)
        ->assertSee('<link rel="alternate" hreflang="x-default" href="'.url('/docs').'">', false)
        ->assertDontSee('name="robots"', false)
        ->assertDontSee('noindex', false);
})->with([
    'English' => [''],
    'Russian' => ['/ru'],
]);

test('the docs overview marks every internal link for in-app navigation', function () {
    docsOverviewWorld();
    $document = docsDocument($this->actingAs(User::factory()->create())->get('/docs')->assertOk());
    $internalLinks = 0;

    foreach ($document->querySelectorAll('a[href]') as $link) {
        $href = $link->getAttribute('href');

        if (str_starts_with($href, url('/').'/') || str_starts_with($href, '/')) {
            expect($link->hasAttribute('data-rodnik-navigate'), $href)->toBeTrue();
            $internalLinks++;
        }
    }

    expect($internalLinks)->toBeGreaterThan(20);
});

test('the docs overview caches slow aggregates as plain values and keeps saved maps live', function () {
    docsOverviewWorld();

    $this->get('/docs')->assertOk()->assertSee('Leading explorer');
    $cached = Cache::get(DocsOverview::CACHE_KEY);

    // The production file cache refuses to unserialize objects.
    expect($cached)->toBeArray()
        ->and(unserialize(serialize($cached), ['allowed_classes' => false]))->toEqual($cached)
        ->and($cached['contributorsCount'])->toBe(2);

    Report::factory()->for(User::factory()->create(['name' => 'Fresh explorer']))->create();
    Map::factory()->create(['title' => 'Brand new map', 'views_count' => 99]);

    $this->get('/docs')->assertOk()->assertDontSee('Fresh explorer')->assertSee('Brand new map')->assertSee('All maps (3)');

    Cache::forget(DocsOverview::CACHE_KEY);

    $this->get('/docs')->assertOk()->assertSee('Fresh explorer')->assertSee('All contributors (3)');
});

test('the docs overview shows the mobile API only to signed-in English readers', function () {
    $user = User::factory()->create();

    $this->get('/docs')->assertOk()->assertDontSee('Mobile API');
    $this->actingAs($user)->get('/docs')->assertOk()
        ->assertSee('Mobile API')
        ->assertSee('href="'.url('/docs/api').'"', false);
    $this->actingAs($user)->get('/ru/docs')->assertOk()
        ->assertDontSee('Мобильный API')
        ->assertDontSee('href="'.url('/docs/api').'"', false);
});

test('the docs overview has empty states before anything is published', function (string $prefix, array $messages) {
    $this->get($prefix.'/docs')->assertOk()->assertSee($messages);
})->with([
    'English' => ['', ['No reports yet.', 'No saved maps yet.', 'Location pages will appear here']],
    'Russian' => ['/ru', ['Пока нет отчётов.', 'Сохранённых карт пока нет.', 'Страницы местоположений появятся здесь']],
]);

test('the docs navigation starts with the overview and lists saved maps', function (string $prefix, string $overview, string $maps, string $locations) {
    $document = docsDocument($this->get($prefix.'/docs')->assertOk());
    $links = $document->querySelectorAll('#docs-navigation nav a');
    $current = $document->querySelectorAll('#docs-navigation nav a[aria-current="page"]');
    $mapLinks = $document->querySelectorAll('#docs-navigation nav a[href="'.$prefix.'/docs/maps"]');
    $locationLinks = $document->querySelectorAll('#docs-navigation nav a[href="'.url($prefix.'/docs/countries').'"]');

    expect($links->item(0)->getAttribute('href'))->toBe($prefix.'/docs')
        ->and($links->item(0)->textContent)->toContain($overview)
        ->and($current->length)->toBe(1)
        ->and($current->item(0)->getAttribute('href'))->toBe($prefix.'/docs')
        ->and($mapLinks->length)->toBe(1)
        ->and($mapLinks->item(0)->textContent)->toContain($maps)
        ->and($locationLinks->length)->toBe(1)
        ->and($locationLinks->item(0)->textContent)->toContain($locations);

    $current = docsDocument($this->get($prefix.'/docs/maps')->assertOk())
        ->querySelectorAll('#docs-navigation nav a[aria-current="page"]');

    expect($current->length)->toBe(1)
        ->and($current->item(0)->getAttribute('href'))->toBe($prefix.'/docs/maps')
        ->and($current->item(0)->textContent)->toContain($maps);
})->with([
    'English' => ['', 'Overview', 'Saved maps', 'Water sources by location'],
    'Russian' => ['/ru', 'Обзор', 'Сохранённые карты', 'Источники по местоположению'],
]);

test('the header links to the docs instead of browsing by location', function (string $prefix, string $label) {
    $response = $this->get($prefix ?: '/')
        ->assertOk()
        ->assertDontSee('Browse by location')
        ->assertDontSee('Источники по местоположению')
        ->assertDontSee('href="'.url($prefix.'/docs/countries').'"', false)
        ->assertSee('href="'.url($prefix.'/docs/about').'"', false);
    $docs = docsDocument($response)->querySelector('[data-docs-link]');

    expect($docs?->getAttribute('href'))->toBe($prefix.'/docs')
        ->and(mb_trim($docs?->textContent ?? ''))->toBe($label)
        ->and($docs?->hasAttribute('data-rodnik-navigate'))->toBeTrue()
        ->and(mb_strpos($response->getContent(), 'data-docs-link'))
        ->toBeLessThan(mb_strpos($response->getContent(), 'aria-haspopup="listbox"'));

    $this->get('/login')->assertOk()->assertSee('data-docs-link', false);
    $this->actingAs(User::factory()->create(['locale' => $prefix === '' ? 'en' : 'ru']))
        ->get($prefix.'/user/maps')
        ->assertOk()
        ->assertSee('href="'.$prefix.'/docs" data-rodnik-navigate data-docs-link', false)
        ->assertSee('>'.$label.'</a>', false);
})->with([
    'English' => ['', 'Docs'],
    'Russian' => ['/ru', 'Инфо'],
]);
