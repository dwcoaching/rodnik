<?php

declare(strict_types=1);

use App\Models\City;
use App\Models\Country;
use App\Models\Region;
use App\Models\Spring;
use App\Support\DuoUrl;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

function placeSearchCountry(array $attributes = []): Country
{
    return Country::create(array_merge([
        'code' => 'RU', 'name' => 'Russia', 'name_ru' => 'Россия', 'slug' => 'russia-'.Str::random(6), 'is_active' => true,
        'bbox_west' => 19.6, 'bbox_south' => 41.2, 'bbox_east' => 190.4, 'bbox_north' => 81.9,
    ], $attributes));
}

function placeSearchRegion(Country $country, array $attributes = []): Region
{
    return Region::create(array_merge([
        'source_key' => 'test:'.Str::uuid(), 'source_country_code' => $country->code, 'admin1_code' => '47', 'country_id' => $country->id,
        'name' => 'Moscow Oblast', 'name_ru' => 'Московская область', 'slug' => 'moscow-oblast-'.Str::random(6), 'is_active' => true,
        'bbox_west' => 35.1, 'bbox_south' => 54.2, 'bbox_east' => 40.2, 'bbox_north' => 56.9,
    ], $attributes));
}

function placeSearchCity(Country $country, ?Region $region, array $attributes = []): City
{
    return City::create(array_merge([
        'geonames_id' => fake()->unique()->numberBetween(800_000_000, 900_000_000), 'source_country_code' => $country->code, 'country_id' => $country->id, 'region_id' => $region?->id,
        'name' => 'Moscow', 'name_ru' => 'Москва', 'slug' => 'moscow-'.Str::random(6), 'latitude' => 55.7522, 'longitude' => 37.6156,
        'population' => 12_000_000, 'is_active' => true,
    ], $attributes));
}

function placeSearchSpring(?City $city, array $attributes = []): Spring
{
    return Spring::withoutEvents(fn (): Spring => Spring::factory()->create(array_merge([
        'nearest_city_id' => $city?->id,
        'country_id' => $city?->country_id,
        'region_id' => $city?->region_id,
        'latitude' => 55.75,
        'longitude' => 37.62,
    ], $attributes)));
}

function placeSearch(string $query, string $prefix = ''): TestResponse
{
    return test()->getJson($prefix.'/search/places?'.http_build_query(['q' => $query]))->assertOk();
}

/** @return list<string> */
function placeSearchNames(TestResponse $response): array
{
    return array_map(fn (array $result): string => $result['type'].':'.$result['name'], $response->json('results'));
}

test('cities match English and Russian name prefixes in both locales', function (string $prefix, string $query, array $expected) {
    $country = placeSearchCountry();
    $region = placeSearchRegion($country);
    $city = placeSearchCity($country, $region);
    $visible = placeSearchSpring($city);
    placeSearchSpring($city);
    placeSearchSpring($city, ['hidden_at' => now()]);
    placeSearchSpring($city, ['redirect_to_spring_id' => $visible->id]);

    $response = placeSearch($query, $prefix);

    expect($response->json('query'))->toBe($query)
        ->and($response->json('corrected'))->toBeNull()
        ->and($response->json('results.0'))->toBe(array_merge([
            'type' => 'city',
            'group' => 'places',
            'id' => $city->id,
        ], $expected, [
            'sources_count' => 2,
            'sources_label' => $prefix === '' ? '2 water sources' : '2 источника воды',
            'url' => url($prefix.'/docs/cities/'.$city->slug),
            'center' => [37.6156, 55.7522],
            'bounds' => null,
            'zoom' => 11,
        ]));
})->with([
    'English name in English' => ['', 'mos', ['name' => 'Moscow', 'alt_name' => null, 'context' => 'Moscow Oblast, Russia']],
    'Russian name in English' => ['', 'мос', ['name' => 'Moscow', 'alt_name' => 'Москва', 'context' => 'Moscow Oblast, Russia']],
    'Russian name in Russian' => ['/ru', 'мос', ['name' => 'Москва', 'alt_name' => null, 'context' => 'Московская область, Россия']],
    'English name in Russian' => ['/ru', 'mos', ['name' => 'Москва', 'alt_name' => 'Moscow', 'context' => 'Московская область, Россия']],
]);

test('countries and regions return bounds and centers only when active with bounds', function () {
    $fiji = placeSearchCountry(['code' => 'FJ', 'name' => 'Fiji', 'name_ru' => 'Фиджи', 'bbox_west' => 178, 'bbox_south' => -21, 'bbox_east' => 184, 'bbox_north' => -12]);
    placeSearchCountry(['code' => 'XF', 'name' => 'Fijian Former', 'is_active' => false, 'bbox_west' => 1, 'bbox_south' => 1, 'bbox_east' => 2, 'bbox_north' => 2]);
    placeSearchCountry(['code' => 'XU', 'name' => 'Fijian Unbounded', 'bbox_west' => null, 'bbox_south' => null, 'bbox_east' => null, 'bbox_north' => null]);
    $eastern = placeSearchRegion($fiji, ['name' => 'Eastern Fiji', 'name_ru' => 'Восточное Фиджи', 'bbox_west' => 178.5, 'bbox_south' => -20, 'bbox_east' => 181, 'bbox_north' => -16]);
    placeSearchRegion($fiji, ['name' => 'Western Fiji', 'bbox_west' => null, 'bbox_south' => null, 'bbox_east' => null, 'bbox_north' => null]);
    placeSearchRegion($fiji, ['name' => 'Northern Fiji', 'is_active' => false]);
    placeSearchSpring(null, ['country_id' => $fiji->id, 'region_id' => $eastern->id]);
    placeSearchSpring(null, ['country_id' => $fiji->id, 'region_id' => null, 'hidden_at' => now()]);

    $response = placeSearch('fiji');

    expect(placeSearchNames($response))->toBe(['country:Fiji', 'region:Eastern Fiji'])
        ->and($response->json('results.0'))->toBe([
            'type' => 'country', 'group' => 'places', 'id' => $fiji->id, 'name' => 'Fiji', 'alt_name' => null, 'context' => null,
            'sources_count' => 1, 'sources_label' => '1 water source', 'url' => url('/docs/countries/'.$fiji->slug),
            'center' => [-179.0, -16.5], 'bounds' => [178.0, -21.0, 184.0, -12.0], 'zoom' => null,
        ])
        ->and($response->json('results.1'))->toMatchArray([
            'type' => 'region', 'context' => 'Fiji', 'sources_count' => 1, 'url' => url('/docs/regions/'.$eastern->slug),
            'center' => [179.75, -18.0], 'bounds' => [178.5, -20.0, 181.0, -16.0], 'zoom' => null,
        ])
        ->and(Cache::get('place-search:sources:country:'.$fiji->id))->toBe(1)
        ->and(placeSearch('восточ', '/ru')->json('results.0'))->toMatchArray([
            'name' => 'Восточное Фиджи', 'context' => 'Фиджи', 'url' => url('/ru/docs/regions/'.$eastern->slug),
        ]);
});

test('places without visible sources have no page link', function () {
    $country = placeSearchCountry(['bbox_west' => null, 'bbox_south' => null, 'bbox_east' => null, 'bbox_north' => null]);
    $city = placeSearchCity($country, null, ['name' => 'Emptyville', 'name_ru' => null, 'population' => null]);
    placeSearchSpring($city, ['hidden_at' => now()]);

    expect(placeSearch('emptyv')->json('results'))->toBe([[
        'type' => 'city', 'group' => 'places', 'id' => $city->id, 'name' => 'Emptyville', 'alt_name' => null, 'context' => 'Russia',
        'sources_count' => 0, 'sources_label' => null, 'url' => null,
        'center' => [37.6156, 55.7522], 'bounds' => null, 'zoom' => 13,
    ]]);
});

test('ranking prefers larger places, exact names and more water sources', function () {
    $country = placeSearchCountry(['bbox_west' => null, 'bbox_south' => null, 'bbox_east' => null, 'bbox_north' => null]);
    placeSearchCity($country, null, ['name' => 'Bergamo', 'name_ru' => 'Бергамо', 'population' => 120_000]);
    placeSearchCity($country, null, ['name' => 'Bergen', 'name_ru' => 'Берген', 'population' => 285_000]);
    placeSearchCity($country, null, ['name' => 'Tverskoy', 'population' => 2_000_000]);
    placeSearchCity($country, null, ['name' => 'Tver', 'population' => 400_000]);
    placeSearchCity($country, null, ['name' => 'Springdale', 'population' => 50_000]);
    $springfield = placeSearchCity($country, null, ['name' => 'Springfield', 'population' => 50_000]);
    placeSearchSpring($springfield);
    placeSearchSpring($springfield);
    placeSearchCity($country, null, ['name' => 'Oryol', 'name_ru' => 'Орёл', 'population' => 300_000]);
    placeSearchCity($country, null, ['name' => 'Orelia', 'name_ru' => 'Орелия', 'population' => 1_500_000]);

    expect(placeSearchNames(placeSearch('berg')))->toBe(['city:Bergen', 'city:Bergamo'])
        ->and(placeSearchNames(placeSearch('tver')))->toBe(['city:Tver', 'city:Tverskoy'])
        ->and(placeSearchNames(placeSearch('spring')))->toBe(['city:Springfield', 'city:Springdale'])
        ->and(placeSearchNames(placeSearch('орел', '/ru')))->toBe(['city:Орёл', 'city:Орелия']);
});

test('area source counts are weighed against settlement counts', function () {
    $country = placeSearchCountry();
    $region = placeSearchRegion($country, ['name' => 'Krasnodar Krai', 'name_ru' => 'Краснодарский край']);
    $city = placeSearchCity($country, $region, ['name' => 'Krasnodar', 'name_ru' => 'Краснодар', 'population' => 750_000]);
    placeSearchSpring($city);
    // With equal weights these 100 sources would rank the krai above the exact settlement name.
    Spring::withoutEvents(fn () => Spring::factory()->count(100)->create(['country_id' => $country->id, 'region_id' => $region->id]));

    expect(placeSearchNames(placeSearch('krasnodar')))->toBe(['city:Krasnodar', 'region:Krasnodar Krai']);
});

test('spring id queries return the visible final target first', function () {
    $country = placeSearchCountry();
    $city = placeSearchCity($country, placeSearchRegion($country));
    $spring = placeSearchSpring($city, ['id' => 7, 'name' => 'Holy spring', 'latitude' => 55.5, 'longitude' => 37.25]);
    $redirected = placeSearchSpring($city, ['redirect_to_spring_id' => $spring->id]);
    $chained = placeSearchSpring($city, ['redirect_to_spring_id' => $redirected->id]);
    $hidden = placeSearchSpring($city, ['hidden_at' => now()]);
    $hiddenTarget = placeSearchSpring($city, ['hidden_at' => now()]);
    $toHidden = placeSearchSpring($city, ['redirect_to_spring_id' => $hiddenTarget->id]);
    $unnamed = placeSearchSpring(null, ['name' => null, 'type' => 'Water well', 'country_id' => $country->id]);
    $expected = [
        'type' => 'spring', 'group' => 'springs', 'id' => 7, 'name' => 'Holy spring', 'alt_name' => null, 'context' => 'Moscow, Russia',
        'sources_count' => null, 'sources_label' => null, 'url' => app(DuoUrl::class)->absolute(['spring' => 7], 'en'),
        'center' => [37.25, 55.5], 'bounds' => null, 'zoom' => null,
    ];

    foreach (['7', '#7', '# 7', ' #7 ', '№7', '№ 7', '#'.$redirected->id, (string) $chained->id] as $query) {
        expect(placeSearch($query)->json('results'))->toBe([$expected]);
    }

    expect(placeSearch((string) $hidden->id)->json('results'))->toBe([])
        ->and(placeSearch((string) $toHidden->id)->json('results'))->toBe([])
        ->and(placeSearch('999999')->json('results'))->toBe([])
        ->and(placeSearch('№7', '/ru')->json('results.0'))->toMatchArray([
            'context' => 'Москва, Россия', 'url' => app(DuoUrl::class)->absolute(['spring' => 7], 'ru'),
        ])
        ->and(placeSearch('#'.$unnamed->id, '/ru')->json('results.0'))->toMatchArray(['name' => 'Колодец', 'context' => 'Россия'])
        ->and(placeSearch('#'.$unnamed->id)->json('results.0.name'))->toBe('Water well');
});

test('queries typed with the other keyboard layout are corrected', function () {
    $country = placeSearchCountry(['bbox_west' => null, 'bbox_south' => null, 'bbox_east' => null, 'bbox_north' => null]);
    placeSearchCity($country, null);
    placeSearchCity($country, null, ['name' => 'Khabarovsk', 'name_ru' => 'Хабаровск', 'population' => 600_000]);

    $wrongRussian = placeSearch(' Vjcrdf ');
    $wrongEnglish = placeSearch('ьщысщц', '/ru');

    expect($wrongRussian->json('query'))->toBe('Vjcrdf')
        ->and($wrongRussian->json('corrected'))->toBe('Москва')
        ->and($wrongRussian->json('results.0'))->toMatchArray(['name' => 'Moscow', 'alt_name' => 'Москва'])
        ->and($wrongEnglish->json('corrected'))->toBe('moscow')
        ->and($wrongEnglish->json('results.0'))->toMatchArray(['name' => 'Москва', 'alt_name' => 'Moscow'])
        ->and(placeSearch('[f,fhjdcr')->json('corrected'))->toBe('хабаровск')
        ->and(placeSearch('mos')->json('corrected'))->toBeNull();

    foreach (['vjcква', 'zzzz', 'münchen'] as $query) {
        expect(placeSearch($query)->json())->toBe(['query' => $query, 'corrected' => null, 'results' => []]);
    }
});

test('short and malformed queries return no results', function () {
    placeSearchCity(placeSearchCountry(), null);

    foreach (['', 'm', ' m ', '-', '- m -'] as $query) {
        expect(placeSearch($query)->json('results'))->toBe([]);
    }

    $this->getJson('/search/places')->assertOk()->assertExactJson(['query' => '', 'corrected' => null, 'results' => []]);
    $this->getJson('/search/places?q[]=mos')->assertOk()->assertJsonPath('results', []);
    expect(placeSearch("  mos\x00\u{200B}cow \n ")->json())->toMatchArray(['query' => 'moscow'])
        ->and(placeSearch(" mo \t sc ")->json('query'))->toBe('mo sc')
        ->and(placeSearch(str_repeat('ab', 80))->json('query'))->toBe(str_repeat('ab', 50));
});

test('LIKE wildcards in queries match literally', function () {
    $country = placeSearchCountry(['bbox_west' => null, 'bbox_south' => null, 'bbox_east' => null, 'bbox_north' => null]);
    placeSearchCity($country, null);
    placeSearchCity($country, null, ['name' => '100% Village', 'name_ru' => null]);

    foreach (['_o', '%c', '%%', '__', '\\%', 'mo%', 'mo_'] as $query) {
        expect(placeSearch($query)->json('results'))->toBe([]);
    }

    expect(placeSearchNames(placeSearch('100%')))->toBe(['city:100% Village'])
        ->and(placeSearchNames(placeSearch('mo')))->toBe(['city:Moscow']);
});

test('spaces, hyphens and apostrophes match each other in place names', function () {
    $country = placeSearchCountry(['bbox_west' => null, 'bbox_south' => null, 'bbox_east' => null, 'bbox_north' => null]);
    placeSearchCity($country, null, ['name' => 'Rostov-na-Donu', 'name_ru' => 'Ростов-на-Дону', 'population' => 1_100_000]);
    placeSearchCity($country, null, ['name' => 'Sankt Peterburg', 'name_ru' => 'Санкт Петербург', 'population' => 5_000_000]);
    placeSearchCity($country, null, ['name' => 'Xi’an', 'name_ru' => 'Сиань', 'population' => 13_000_000]);
    placeSearchCity($country, null, ['name' => "'s-Hertogenbosch", 'name_ru' => 'Хертогенбос', 'population' => 150_000]);

    foreach (['rostov na', 'Rostov - na', 'rostov-na-donu', 'ростов на дону', 'rostov-'] as $query) {
        expect(placeSearchNames(placeSearch($query)))->toBe(['city:Rostov-na-Donu']);
    }

    foreach (["xi'an", 'xi an', 'xi’an', 'xiʼan', 'xi`an', 'xi-an'] as $query) {
        expect(placeSearchNames(placeSearch($query)))->toBe(['city:Xi’an']);
    }

    expect(placeSearch('rostov na donu')->json('results.0.name'))->toBe('Rostov-na-Donu')
        ->and(placeSearchNames(placeSearch('sankt-peter')))->toBe(['city:Sankt Peterburg'])
        ->and(placeSearchNames(placeSearch('санкт-петербург', '/ru')))->toBe(['city:Санкт Петербург'])
        ->and(placeSearchNames(placeSearch("'s-herto")))->toBe(["city:'s-Hertogenbosch"]);
});

test('separators match only separators, not letters, in place names', function () {
    $country = placeSearchCountry(['code' => 'SA', 'name' => 'Saudi Arabia', 'name_ru' => 'Саудовская Аравия', 'bbox_west' => 34.5, 'bbox_south' => 16.4, 'bbox_east' => 55.7, 'bbox_north' => 32.2]);
    placeSearchRegion($country, ['name' => 'Norte de Santander Department', 'name_ru' => 'Северный Сантандер']);
    placeSearchCity($country, null, ['name' => 'St Peter Port', 'name_ru' => 'Сент-Питер-Порт', 'population' => 18_000]);
    placeSearchCity($country, null, ['name' => 'Stapelburg', 'name_ru' => null, 'population' => 1_500_000]);
    placeSearchCity($country, null, ['name' => 'Stepekolk', 'name_ru' => 'Степеколк', 'population' => 900_000]);
    placeSearchCity($country, null, ['name' => 'San Angelo', 'name_ru' => 'Сан-Анджело', 'population' => 100_000]);
    placeSearchCity($country, null, ['name' => 'Santander', 'name_ru' => 'Сантандер', 'population' => 170_000]);
    placeSearchCity($country, null, ['name' => 'A Arnoia', 'name_ru' => null, 'population' => 1_000]);
    placeSearchCity($country, null, ['name' => 'Alameda', 'name_ru' => 'Аламида', 'population' => 78_000]);

    expect(placeSearchNames(placeSearch('st pe')))->toBe(['city:St Peter Port'])
        ->and(placeSearch('ст-пе', '/ru')->json('results'))->toBe([])
        ->and(placeSearchNames(placeSearch('san an')))->toBe(['city:San Angelo'])
        ->and(placeSearchNames(placeSearch('a a')))->toBe(['city:A Arnoia']);
});

test('queries without enough base letters run no full-text search', function () {
    placeSearchCity(placeSearchCountry(), null);
    $fullText = [];
    DB::listen(function (QueryExecuted $query) use (&$fullText): void {
        if (str_contains($query->sql, 'match (')) {
            $fullText[] = $query->bindings[0];
        }
    });

    // Combining acute accents, halfwidth voiced marks, variation selectors, and single letters carrying them.
    foreach (["\u{301}\u{301}\u{301}", 'ﾞﾞﾞ', "\u{FE0E}\u{FE0E}\u{FE0E}", "\u{301}\u{301}\u{301} \u{301}\u{301}\u{301} \u{301}\u{301}\u{301}", "a\u{301}\u{301}\u{301}", 'aﾞﾞ'] as $query) {
        expect(placeSearch($query)->json('results'))->toBe([]);
    }

    placeSearch("mo\u{FE0E}\u{FE0E} xﾞﾞ");
    expect($fullText)->toBe([]);

    placeSearch("mos\u{FE0E}\u{FE0E}");
    expect($fullText[0])->toBe('+mos*');
});

test('long queries search at most four full-text words', function () {
    $fullText = [];
    DB::listen(function (QueryExecuted $query) use (&$fullText): void {
        if (str_contains($query->sql, 'match (')) {
            $fullText[] = $query->bindings[0];
        }
    });

    $query = implode(' ', array_map(fn (int $letter): string => 'ab'.chr($letter), range(ord('a'), ord('y'))));
    expect(str_word_count($query))->toBe(25)
        ->and(placeSearch($query)->json('results'))->toBe([])
        // Settlements and water sources share the words; the second search retypes the query on the Russian layout.
        ->and(array_values(array_unique($fullText)))->toBe(['+aba* +abb* +abc* +abd*', '+фиф* +фии* +фис* +фив*']);
});

test('letters the collation folds are found without diacritics', function () {
    $country = placeSearchCountry(['code' => 'PL', 'name' => 'Poland', 'name_ru' => 'Польша', 'bbox_west' => 14.1, 'bbox_south' => 49.0, 'bbox_east' => 24.2, 'bbox_north' => 54.9]);
    placeSearchRegion($country, ['name' => 'Łódź Voivodeship', 'name_ru' => 'Лодзинское воеводство']);
    placeSearchCity($country, null, ['name' => 'Łódź', 'name_ru' => 'Лодзь', 'population' => 680_000]);
    placeSearchCity($country, null, ['name' => 'Wrocław', 'name_ru' => 'Вроцлав', 'population' => 640_000]);
    placeSearchCity($country, null, ['name' => 'Białystok', 'name_ru' => 'Белосток', 'population' => 300_000]);
    placeSearchCity($country, null, ['name' => 'Tromsø', 'name_ru' => 'Тромсё', 'population' => 77_000]);
    placeSearchCity($country, null, ['name' => 'Ðakovo', 'name_ru' => 'Джаково', 'population' => 20_000]);
    placeSearchCity($country, null, ['name' => 'Yoshkar-Ola', 'name_ru' => 'Йошкар-Ола', 'population' => 280_000]);

    expect(placeSearchNames(placeSearch('lodz')))->toBe(['city:Łódź', 'region:Łódź Voivodeship'])
        ->and(placeSearchNames(placeSearch('wroclaw')))->toBe(['city:Wrocław'])
        ->and(placeSearchNames(placeSearch('bialystok')))->toBe(['city:Białystok'])
        ->and(placeSearchNames(placeSearch('tromso')))->toBe(['city:Tromsø'])
        ->and(placeSearchNames(placeSearch('dakovo')))->toBe(['city:Ðakovo'])
        ->and(placeSearchNames(placeSearch('тромсе', '/ru')))->toBe(['city:Тромсё'])
        ->and(placeSearchNames(placeSearch('йошкар', '/ru')))->toBe(['city:Йошкар-Ола'])
        ->and(placeSearch('иошкар', '/ru')->json('results'))->toBe([]);
});

test('inactive settlements are excluded and results are capped', function () {
    $country = placeSearchCountry(['bbox_west' => null, 'bbox_south' => null, 'bbox_east' => null, 'bbox_north' => null]);
    placeSearchCity($country, null, ['name' => 'Inactive town', 'is_active' => false]);
    foreach (range(1, 10) as $number) {
        placeSearchCity($country, null, ['name' => 'Townsend '.$number, 'population' => $number * 1000]);
    }

    expect(placeSearch('inactive')->json('results'))->toBe([])
        ->and(placeSearchNames(placeSearch('townsend')))->toBe(array_map(fn (int $number): string => 'city:Townsend '.$number, range(10, 5)));
});

test('search responses are privately cacheable and throttled in both locales', function () {
    placeSearch('mos')->assertHeader('X-RateLimit-Limit', '120')
        ->assertHeader('Content-Type', 'application/json');

    $cacheControl = placeSearch('mos', '/ru')->headers->get('Cache-Control');
    expect(explode(', ', $cacheControl))->toEqualCanonicalizing(['private', 'max-age=300']);

    foreach (['places.search' => 'search/places', 'ru.places.search' => 'ru/search/places'] as $name => $uri) {
        $route = Route::getRoutes()->getByName($name);
        expect($route->uri())->toBe($uri)
            ->and($route->gatherMiddleware())->toContain('throttle:120,1,place-search');
    }
});

test('large cities outrank small countries on short prefixes while exact country names still win', function () {
    $country = placeSearchCountry();
    placeSearchCountry(['code' => 'MC', 'name' => 'Monaco', 'name_ru' => 'Монако', 'bbox_west' => 7.4, 'bbox_south' => 43.7, 'bbox_east' => 7.5, 'bbox_north' => 43.8]);
    placeSearchCountry(['code' => 'GE', 'name' => 'Georgia', 'name_ru' => 'Грузия', 'bbox_west' => 40, 'bbox_south' => 41, 'bbox_east' => 46.7, 'bbox_north' => 43.6]);
    placeSearchCity($country, null);
    placeSearchCity($country, null, ['name' => 'Georgia', 'name_ru' => 'Джорджия', 'population' => 3_000_000]);

    expect(placeSearchNames(placeSearch('mo')))->toBe(['city:Moscow', 'country:Monaco'])
        ->and(placeSearchNames(placeSearch('georgia')))->toBe(['country:Georgia', 'city:Georgia']);
});

test('the visible map area finds and prefers its own same-named villages', function () {
    $country = placeSearchCountry();
    foreach (range(1, 30) as $index) {
        placeSearchCity($country, null, ['name' => 'Aleksandrovka', 'population' => 1_000 + $index, 'latitude' => 50.0, 'longitude' => 30.0 + $index / 10]);
    }
    placeSearchCity($country, null, ['name' => 'Aleksandrovsk', 'population' => 40_000, 'latitude' => 59.0, 'longitude' => 57.6]);
    $local = placeSearchCity($country, null, ['name' => 'Aleksandrovka', 'population' => null, 'latitude' => 55.9, 'longitude' => 37.4]);
    $view = fn (string $bbox): TestResponse => test()->getJson('/search/places?'.http_build_query(['q' => 'aleksandrovk', 'bbox' => $bbox]))->assertOk();

    expect(collect(placeSearch('aleksandrovk')->json('results'))->pluck('id'))->not->toContain($local->id)
        ->and($view('37.1,55.5,38.2,56.2')->json('results.0.id'))->toBe($local->id)
        ->and($view('37.1,55.5,60,56.2')->json('results.0.id'))->not->toBe($local->id, 'views wider than 20 degrees are ignored')
        ->and($view('37.1,55.5,38.2')->json('results.0.id'))->not->toBe($local->id)
        ->and($view('37.1,56.2,38.2,55.5')->json('results.0.id'))->not->toBe($local->id)
        ->and($view('190,55.5,200,56.2')->json('results.0.id'))->not->toBe($local->id);
});

test('settlements just outside the view rank above same-sized namesakes far away', function () {
    $country = placeSearchCountry();
    placeSearchCity($country, null, ['name' => 'Ivanovka', 'population' => null, 'latitude' => 45.0, 'longitude' => 40.0]);
    $near = placeSearchCity($country, null, ['name' => 'Ivanovka', 'population' => null, 'latitude' => 55.9, 'longitude' => 37.4]);

    expect(test()->getJson('/search/places?'.http_build_query(['q' => 'ivanovka', 'bbox' => '36,54.5,37,55.5']))->json('results.0.id'))->toBe($near->id);
});

test('views across the antimeridian match settlements on both sides', function () {
    $country = placeSearchCountry();
    $east = placeSearchCity($country, null, ['name' => 'Anadyr', 'population' => null, 'latitude' => 64.7, 'longitude' => 177.5]);
    $west = placeSearchCity($country, null, ['name' => 'Anadyrka', 'population' => null, 'latitude' => 65.5, 'longitude' => -175.0]);
    placeSearchCity($country, null, ['name' => 'Anadyria', 'population' => 9_000, 'latitude' => 10.0, 'longitude' => 10.0]);

    $ids = collect(test()->getJson('/search/places?'.http_build_query(['q' => 'anadyr', 'bbox' => '170,60,-170,70']))->json('results'))->pluck('id')->take(2);

    expect($ids->sort()->values()->all())->toBe(collect([$east->id, $west->id])->sort()->values()->all());
});

test('stress marks in Russian names are not shown', function () {
    $country = placeSearchCountry(['code' => 'GS', 'name' => 'South Georgia', 'name_ru' => 'Ю́жная Гео́ргия', 'bbox_west' => -38, 'bbox_south' => -59.5, 'bbox_east' => -26, 'bbox_north' => -53.9]);
    placeSearchRegion($country, ['name' => 'South Georgia Island', 'name_ru' => 'Гео́ргия о́стров', 'bbox_west' => -38, 'bbox_south' => -55, 'bbox_east' => -35, 'bbox_north' => -54]);

    expect(placeSearchNames(placeSearch('south georgia', '/ru')))->toBe(['country:Южная Георгия', 'region:Георгия остров'])
        ->and(placeSearch('south georgia island', '/ru')->json('results.0'))->toMatchArray(['name' => 'Георгия остров', 'context' => 'Южная Георгия']);
});
