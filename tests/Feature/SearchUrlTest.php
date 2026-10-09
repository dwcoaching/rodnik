<?php

declare(strict_types=1);

use App\Models\City;
use App\Models\Country;
use App\Models\Map;
use App\Models\Region;
use App\Models\Spring;
use App\Support\DuoUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

function searchUrl(string $query, string $prefix = ''): TestResponse
{
    return test()->getJson($prefix.'/search/places?'.http_build_query(['q' => $query]))->assertOk();
}

test('links to a saved map find it on any host, with or without a language and trailing slash', function () {
    $map = Map::factory()->create(['title' => 'Lycian Way', 'slug' => 'Lycian-Way', 'updated_at' => '2026-09-21 10:00:00']);
    Map::factory()->create(['title' => 'https://example.com/maps/Lycian-Way']);
    $expected = [
        'type' => 'map', 'group' => 'maps', 'id' => $map->id, 'name' => 'Lycian Way', 'alt_name' => null, 'context' => '21 Sep 2026',
        'sources_count' => null, 'sources_label' => null, 'url' => url('/maps/Lycian-Way').'/',
        'center' => [37.6173, 55.7558], 'bounds' => null, 'zoom' => 12.0,
    ];

    foreach ([
        'https://rodnik.app/maps/Lycian-Way', 'https://rodnik.app/maps/Lycian-Way/', 'http://localhost:8001/ru/maps/Lycian-Way/#m=12/55.75/37.6',
        'rodnik.app/en/maps/Lycian-Way?ref=chat', 'www.rodnik.app/maps/Lycian-Way/login', '/maps/Lycian-Way', '//rodnik.app/ru/maps/Lycian-Way.',
        'https://example.com/maps/Lycian-Way',
    ] as $link) {
        expect(searchUrl($link)->json())->toBe(['query' => $link, 'corrected' => null, 'results' => [$expected]], $link);
    }

    expect(searchUrl('https://rodnik.app/maps/Lycian-Way', '/ru')->json('results.0'))
        ->toMatchArray(['context' => '21 сент. 2026', 'url' => url('/ru/maps/Lycian-Way').'/'])
        ->and(searchUrl('https://rodnik.app/maps/lycian-way')->json('results'))->toBe([], 'slugs are case-sensitive');
});

test('links to a water source find the visible one it leads to', function () {
    $spring = Spring::withoutEvents(fn (): Spring => Spring::factory()->create(['name' => 'Holy spring', 'latitude' => 55.5, 'longitude' => 37.25]));
    $merged = Spring::withoutEvents(fn (): Spring => Spring::factory()->create(['redirect_to_spring_id' => $spring->id]));
    $hidden = Spring::withoutEvents(fn (): Spring => Spring::factory()->create(['hidden_at' => now()]));
    $expected = [
        'type' => 'spring', 'group' => 'springs', 'id' => $spring->id, 'name' => 'Holy spring', 'alt_name' => null, 'context' => null,
        'sources_count' => null, 'sources_label' => null, 'url' => app(DuoUrl::class)->absolute(['spring' => $spring->id], 'en'),
        'center' => [37.25, 55.5], 'bounds' => null, 'zoom' => null,
    ];

    foreach ([
        "https://rodnik.app/{$spring->id}/", "/{$spring->id}", "rodnik.app/ru/{$merged->id}", "https://rodnik.app/springs/{$spring->id}/history",
        "/{$spring->id}/location/edit", "http://localhost:8001/en/springs/{$spring->id}/edit?x=1", "/springs/{$merged->id}/location/edit",
        // Older links that the site still redirects kept the water source in the query string.
        "https://rodnik.app/?s={$spring->id}", "rodnik.app/ru/?spring={$merged->id}", "/?page[spring]={$spring->id}&page[user]=3",
        "https://rodnik.app/?view[spring]={$spring->id}", "/ru?spring_id={$spring->id}.",
    ] as $link) {
        expect(searchUrl($link)->json('results'))->toBe([$expected], $link);
    }

    expect(searchUrl("/{$spring->id}", '/ru')->json('results.0.url'))->toBe(app(DuoUrl::class)->absolute(['spring' => $spring->id], 'ru'))
        ->and(searchUrl("https://rodnik.app/{$hidden->id}")->json('results'))->toBe([])
        ->and(searchUrl('https://rodnik.app/999999')->json('results'))->toBe([])
        ->and(searchUrl("https://rodnik.app/?s={$hidden->id}")->json('results'))->toBe([])
        ->and(searchUrl('https://rodnik.app/?s=abc')->json('results'))->toBe([])
        ->and(searchUrl('https://rodnik.app/?q=holy')->json('results'))->toBe([], 'other home page links lead to nothing');
});

test('links to place pages find active places, on new and old addresses', function () {
    $country = Country::create([
        'code' => 'PL', 'name' => 'Poland', 'name_ru' => 'Польша', 'slug' => 'poland-pl', 'is_active' => true,
        'bbox_west' => 14.1, 'bbox_south' => 49.0, 'bbox_east' => 24.2, 'bbox_north' => 54.9,
    ]);
    $region = Region::create([
        'source_key' => 'test:lodz', 'source_country_code' => 'PL', 'admin1_code' => '74', 'country_id' => $country->id, 'name' => 'Łódź Voivodeship',
        'name_ru' => 'Лодзинское воеводство', 'slug' => 'lodz-voivodeship', 'is_active' => true,
        'bbox_west' => 18.0, 'bbox_south' => 50.8, 'bbox_east' => 20.7, 'bbox_north' => 52.4,
    ]);
    $city = City::create([
        'geonames_id' => fake()->unique()->numberBetween(800_000_000, 900_000_000), 'source_country_code' => 'PL', 'country_id' => $country->id, 'region_id' => $region->id, 'name' => 'Łódź',
        'name_ru' => 'Лодзь', 'slug' => 'lodz-3093133', 'latitude' => 51.77, 'longitude' => 19.47, 'population' => 680_000, 'is_active' => true,
    ]);
    City::create([
        'geonames_id' => fake()->unique()->numberBetween(800_000_000, 900_000_000), 'source_country_code' => 'PL', 'country_id' => $country->id, 'name' => 'Former', 'slug' => 'former-1',
        'latitude' => 51.0, 'longitude' => 19.0, 'population' => 10, 'is_active' => false,
    ]);
    Country::create(['code' => 'XU', 'name' => 'Unbounded', 'slug' => 'unbounded-xu', 'is_active' => true]);
    Spring::withoutEvents(fn () => Spring::factory()->create(['nearest_city_id' => $city->id, 'region_id' => $region->id, 'country_id' => $country->id]));

    foreach (['/docs/cities/lodz-3093133', 'https://rodnik.app/cities/lodz-3093133/', 'rodnik.app/ru/docs/cities/lodz-3093133'] as $link) {
        expect(searchUrl($link)->json('results'))->toBe([[
            'type' => 'city', 'group' => 'places', 'id' => $city->id, 'name' => 'Łódź', 'alt_name' => null, 'context' => 'Łódź Voivodeship, Poland',
            'sources_count' => 1, 'sources_label' => '1 water source', 'url' => url('/docs/cities/lodz-3093133'),
            'center' => [19.47, 51.77], 'bounds' => null, 'zoom' => 12,
        ]], $link);
    }

    expect(searchUrl('https://rodnik.app/ru/regions/lodz-voivodeship', '/ru')->json('results.0'))->toMatchArray([
        'type' => 'region', 'name' => 'Лодзинское воеводство', 'context' => 'Польша', 'url' => url('/ru/docs/regions/lodz-voivodeship'),
        'bounds' => [18.0, 50.8, 20.7, 52.4],
    ])
        ->and(searchUrl('/docs/countries/poland-pl')->json('results.0'))->toMatchArray(['type' => 'country', 'id' => $country->id, 'sources_count' => 1])
        ->and(searchUrl('/countries/poland-pl')->json('results.0.id'))->toBe($country->id)
        ->and(searchUrl('/docs/cities/former-1')->json('results'))->toBe([])
        ->and(searchUrl('/docs/countries/unbounded-xu')->json('results'))->toBe([]);
});

test('links to anything else find nothing, and their words are not searched', function () {
    $country = Country::create(['code' => 'RU', 'name' => 'Russia', 'name_ru' => 'Россия', 'slug' => 'russia-ru', 'is_active' => true]);
    City::create([
        'geonames_id' => fake()->unique()->numberBetween(800_000_000, 900_000_000), 'source_country_code' => 'RU', 'country_id' => $country->id, 'name' => 'About', 'slug' => 'about-1',
        'latitude' => 51.0, 'longitude' => 19.0, 'population' => 10_000, 'is_active' => true,
    ]);
    Map::factory()->create(['title' => 'rodnik.app/about']);

    foreach (['https://rodnik.app/', 'https://rodnik.app', 'rodnik.app/about', '/about', '/docs/countries', 'https://example.com/users/5', '/maps/x', 'vjcrdf.ru/'] as $link) {
        expect(searchUrl($link)->json())->toBe(['query' => $link, 'corrected' => null, 'results' => []], $link);
    }

    // As plain words, both are found: "about" starts a word of the map title after its slash.
    expect(array_column(searchUrl('about')->json('results'), 'name'))->toBe(['About', 'rodnik.app/about'])
        ->and(array_column(searchUrl('rodnik.app about')->json('results'), 'name'))->toBe(['rodnik.app/about']);
});

test('long links are resolved before the query is shortened', function () {
    $slug = str_repeat('a', 80);
    $map = Map::factory()->create(['title' => 'Long', 'slug' => $slug]);
    $link = 'https://www.rodnik.app/ru/maps/'.$slug.'/#m=12/55.75/37.6&l=topo';

    $response = searchUrl($link);

    expect($response->json('results.0.id'))->toBe($map->id)
        ->and(mb_strlen($response->json('query')))->toBe(100);
});
