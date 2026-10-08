<?php

declare(strict_types=1);

use App\Models\City;
use App\Models\Country;
use App\Models\Map;
use App\Models\Region;
use App\Models\Report;
use App\Models\Spring;
use App\Models\User;
use App\Support\DuoUrl;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

uses(DatabaseTruncation::class);

beforeEach(function () {
    // Tests\TestCase still opens a transaction, and InnoDB full-text indexes only see committed rows.
    DB::commit();
});

afterEach(function () {
    // Truncation only runs before tests in this file; leave no committed rows for later files.
    Schema::withoutForeignKeyConstraints(function (): void {
        foreach (['reports', 'springs', 'maps', 'users', 'cities', 'regions', 'countries'] as $table) {
            DB::table($table)->truncate();
        }
    });
});

function springNameSearchSpring(string $name, array $attributes = []): Spring
{
    return Spring::withoutEvents(fn (): Spring => Spring::factory()->create(array_merge([
        'name' => $name, 'type' => 'Spring', 'latitude' => 55.75, 'longitude' => 37.62,
    ], $attributes)));
}

function springNameSearchReports(Spring $spring, int $count, array $attributes = []): void
{
    $user = User::query()->first() ?? User::factory()->create();
    Report::factory()->count($count)->create(array_merge(['spring_id' => $spring->id, 'user_id' => $user->id], $attributes));
}

function springNameSearch(string $query, string $prefix = '', ?string $bbox = null): TestResponse
{
    return test()->getJson($prefix.'/search/places?'.http_build_query(array_filter(['q' => $query, 'bbox' => $bbox])))->assertOk();
}

/** @return list<int> */
function springNameSearchIds(TestResponse $response): array
{
    return array_column($response->json('results'), 'id');
}

/** @return list<string> */
function springNameSearchGroups(TestResponse $response): array
{
    return array_map(fn (array $result): string => $result['group'].':'.$result['name'], $response->json('results'));
}

test('water sources are found by any word of their name in both locales', function () {
    $country = Country::create(['code' => 'RU', 'name' => 'Russia', 'name_ru' => 'Россия', 'slug' => 'russia-ru', 'is_active' => true]);
    $region = Region::create([
        'source_key' => 'test:moscow-oblast', 'source_country_code' => 'RU', 'admin1_code' => '47', 'country_id' => $country->id,
        'name' => 'Moscow Oblast', 'name_ru' => 'Московская область', 'slug' => 'moscow-oblast', 'is_active' => true,
    ]);
    $city = City::create([
        'source_key' => 'test:mytishchi', 'source_country_code' => 'RU', 'country_id' => $country->id, 'region_id' => $region->id,
        'name' => 'Mytishchi', 'name_ru' => 'Мытищи', 'slug' => 'mytishchi', 'latitude' => 55.91, 'longitude' => 37.73, 'population' => 200_000, 'is_active' => true,
    ]);
    $spring = springNameSearchSpring('Святой источник', [
        'nearest_city_id' => $city->id, 'region_id' => $region->id, 'country_id' => $country->id, 'latitude' => 55.9, 'longitude' => 37.7,
    ]);
    springNameSearchReports($spring, 2);
    springNameSearchReports($spring, 1, ['hidden_at' => now()]);
    springNameSearchReports($spring, 1, ['from_osm' => true]);
    $unnamedType = springNameSearchSpring('Старый источник', ['type' => null, 'latitude' => 10.0, 'longitude' => 10.0]);

    expect(springNameSearch('источник', '/ru')->json('results'))->toBe([
        [
            'type' => 'spring', 'group' => 'springs', 'id' => $spring->id, 'name' => 'Святой источник', 'alt_name' => null,
            'context' => 'Родник · Мытищи, Московская область, Россия', 'sources_count' => null, 'sources_label' => '2 отчёта',
            'url' => app(DuoUrl::class)->absolute(['spring' => $spring->id], 'ru'), 'center' => [37.7, 55.9], 'bounds' => null, 'zoom' => null,
        ],
        [
            'type' => 'spring', 'group' => 'springs', 'id' => $unnamedType->id, 'name' => 'Старый источник', 'alt_name' => null,
            'context' => 'Источник воды', 'sources_count' => null, 'sources_label' => null,
            'url' => app(DuoUrl::class)->absolute(['spring' => $unnamedType->id], 'ru'), 'center' => [10.0, 10.0], 'bounds' => null, 'zoom' => null,
        ],
    ])
        ->and(springNameSearch('святой ист')->json('results.0'))->toMatchArray([
            'id' => $spring->id, 'context' => 'Spring · Mytishchi, Moscow Oblast, Russia', 'sources_label' => '2 reports',
            'url' => app(DuoUrl::class)->absolute(['spring' => $spring->id], 'en'),
        ])
        ->and(springNameSearchIds(springNameSearch('ист святой')))->toBe([$spring->id]);
});

test('a source named like its type and a city named like its region are not repeated', function () {
    $country = Country::create(['code' => 'RU', 'name' => 'Russia', 'name_ru' => 'Россия', 'slug' => 'russia-ru', 'is_active' => true]);
    $region = Region::create([
        'source_key' => 'test:moscow', 'source_country_code' => 'RU', 'admin1_code' => '48', 'country_id' => $country->id,
        'name' => 'Moscow', 'name_ru' => 'Москва', 'slug' => 'moscow', 'is_active' => true,
    ]);
    $city = City::create([
        'source_key' => 'test:moscow-city', 'source_country_code' => 'RU', 'country_id' => $country->id, 'region_id' => $region->id,
        'name' => 'Moscow', 'name_ru' => 'Москва', 'slug' => 'moscow-city', 'latitude' => 55.75, 'longitude' => 37.62, 'population' => 12_000_000, 'is_active' => true,
    ]);
    springNameSearchSpring('Родник', ['nearest_city_id' => $city->id, 'region_id' => $region->id, 'country_id' => $country->id]);

    expect(springNameSearch('родник', '/ru')->json('results.0.context'))->toBe('Москва, Россия')
        ->and(springNameSearch('родник')->json('results.0.context'))->toBe('Spring · Moscow, Russia');
});

test('water sources in the visible map area rank first, and the most reported ones without a view', function () {
    $far = springNameSearchSpring('Holy spring', ['latitude' => 45.0, 'longitude' => 40.0]);
    springNameSearchReports($far, 3);
    $inside = springNameSearchSpring('Holy spring', ['latitude' => 55.8, 'longitude' => 37.5]);
    $near = springNameSearchSpring('Holy spring', ['latitude' => 56.6, 'longitude' => 37.5]);
    $elsewhere = springNameSearchSpring('Holy spring', ['latitude' => 10.0, 'longitude' => 10.0]);

    expect(springNameSearchIds(springNameSearch('holy spring')))->toBe([$far->id, $inside->id, $near->id, $elsewhere->id])
        ->and(springNameSearchIds(springNameSearch('holy spring', bbox: '37.1,55.5,38.2,56.2')))->toBe([$inside->id, $near->id, $far->id, $elsewhere->id])
        ->and(springNameSearch('holy spring')->json('results.0.sources_label'))->toBe('3 reports')
        ->and(springNameSearch('holy spring', '/ru')->json('results.0.sources_label'))->toBe('3 отчёта');
});

test('the visible map area finds its own sources among many with the same name', function () {
    // More namesakes than the candidates ranked for every query.
    Spring::withoutEvents(fn () => Spring::factory()->count(80)->create(['name' => 'Puits', 'latitude' => 45.0, 'longitude' => 2.0]));
    $local = springNameSearchSpring('Puits', ['latitude' => 48.85, 'longitude' => 2.35]);

    expect(springNameSearchIds(springNameSearch('puits')))->not->toContain($local->id)
        ->and(springNameSearchIds(springNameSearch('puits', bbox: '2.2,48.8,2.5,48.9'))[0])->toBe($local->id);
});

test('hidden and merged water sources and short queries are not searched by name', function () {
    $visible = springNameSearchSpring('Kristall');
    springNameSearchSpring('Kristall', ['hidden_at' => now()]);
    springNameSearchSpring('Kristall', ['redirect_to_spring_id' => $visible->id]);
    springNameSearchSpring('Kristall', ['latitude' => null, 'longitude' => null]);

    expect(springNameSearchIds(springNameSearch('kristall')))->toBe([$visible->id])
        ->and(springNameSearchIds(springNameSearch('kri')))->toBe([$visible->id])
        ->and(springNameSearch('kr')->json('results'))->toBe([])
        ->and(springNameSearch("k\u{301}r\u{301}")->json('results'))->toBe([]);
});

test('exact names come before prefixes and word starts, and other full-text matches are dropped', function () {
    springNameSearchSpring('Big Spring');
    springNameSearchSpring('Springfield well');
    springNameSearchSpring('Spring');
    springNameSearchSpring('Hot springs');
    springNameSearchSpring('Spring Creek');
    springNameSearchSpring('Источник Spring');

    $names = fn (string $query): array => array_column(springNameSearch($query)->json('results'), 'name');

    expect($names('spring'))->toBe(['Spring', 'Spring Creek', 'Springfield well', 'Big Spring', 'Hot springs'])
        ->and($names('spring cr'))->toBe(['Spring Creek'])
        ->and($names('creek spr'))->toBe(['Spring Creek'])
        ->and($names('pring'))->toBe([]);
});

test('a number finds that water source first, and only it when written with a number sign', function () {
    $numbered = springNameSearchSpring('Ключ', ['id' => 4321]);
    $named = springNameSearchSpring('4321 Fountain');

    expect(springNameSearchIds(springNameSearch('4321')))->toBe([$numbered->id, $named->id])
        ->and(springNameSearch('4321')->json('results.0.context'))->toBeNull()
        ->and(springNameSearchIds(springNameSearch('#4321')))->toBe([$numbered->id])
        ->and(springNameSearchIds(springNameSearch('№ 4321')))->toBe([$numbered->id])
        ->and(springNameSearch('#9999')->json('results'))->toBe([]);
});

test('names typed with the other keyboard layout are corrected', function () {
    $spring = springNameSearchSpring('Родник');

    $response = springNameSearch('hjlybr', '/ru');

    expect($response->json('corrected'))->toBe('родник')
        ->and(springNameSearchIds($response))->toBe([$spring->id]);
});

test('groups stay together, the closest match comes first and each group is capped', function () {
    $country = Country::create(['code' => 'RU', 'name' => 'Russia', 'name_ru' => 'Россия', 'slug' => 'russia-ru', 'is_active' => true]);
    $city = fn (string $name, int $population): City => City::create([
        'source_key' => 'test:'.Str::uuid(), 'source_country_code' => 'RU', 'country_id' => $country->id, 'name' => $name, 'name_ru' => null,
        'slug' => Str::slug($name).'-'.Str::random(6), 'latitude' => 56.0, 'longitude' => 38.0, 'population' => $population, 'is_active' => true,
    ]);
    $city('Rodniki', 25_000);
    springNameSearchSpring('Rodnik');
    Map::factory()->create(['title' => 'Rodnik weekend']);

    expect(springNameSearchGroups(springNameSearch('rodnik')))->toBe(['springs:Rodnik', 'places:Rodniki', 'maps:Rodnik weekend'])
        ->and(springNameSearchGroups(springNameSearch('rodn')))->toBe(['places:Rodniki', 'springs:Rodnik', 'maps:Rodnik weekend'])
        ->and(springNameSearchGroups(springNameSearch('rodniki')))->toBe(['places:Rodniki']);

    Map::factory()->create(['title' => 'Rodn']);
    expect(springNameSearchGroups(springNameSearch('rodn')))->toBe(['maps:Rodn', 'maps:Rodnik weekend', 'places:Rodniki', 'springs:Rodnik']);

    foreach (range(1, 7) as $index) {
        $city('Rodnikovo '.$index, 1_000 * $index);
        springNameSearchSpring('Rodnik '.$index);
        Map::factory()->create(['title' => 'Rodnik route '.$index]);
    }

    $groups = array_count_values(array_column(springNameSearch('rodnik')->json('results'), 'group'));
    $order = array_values(array_unique(array_column(springNameSearch('rodnik')->json('results'), 'group')));

    expect($groups)->toBe(['springs' => 5, 'places' => 6, 'maps' => 4])
        ->and($order)->toBe(['springs', 'places', 'maps']);
});

test('short words and stopwords that the full-text index skips still have to match', function () {
    // More full-text matches for "spring" than the candidate limit, none with a word starting "st": the short
    // word must narrow them in the database, not after the limit.
    foreach (range(1, 70) as $index) {
        springNameSearchSpring('Cold Spring '.$index, ['latitude' => 40.0, 'longitude' => -100.0 - $index / 100]);
    }
    $saint = springNameSearchSpring("St. Mary's Spring", ['latitude' => 41.0, 'longitude' => -101.0]);
    $key = springNameSearchSpring('Святой ключ', ['latitude' => 56.0, 'longitude' => 38.0]);
    springNameSearchSpring('Ключевой', ['latitude' => 56.1, 'longitude' => 38.1]);

    expect(springNameSearchIds(springNameSearch('st spring')))->toBe([$saint->id])
        ->and(springNameSearchIds(springNameSearch('spring st')))->toBe([$saint->id])
        ->and(springNameSearchIds(springNameSearch('св ключ', '/ru')))->toBe([$key->id])
        ->and(springNameSearchIds(springNameSearch('the spring of st')))->not->toContain($saint->id);
});

test('names are found without the letters the collation folds', function () {
    $zrodlo = springNameSearchSpring('Źródło pod lasem');
    springNameSearchSpring('Йодный источник');

    expect(springNameSearchIds(springNameSearch('zrodlo')))->toBe([$zrodlo->id])
        ->and(springNameSearchIds(springNameSearch('иодный', '/ru')))->toBe([], 'й stays distinct from и');
});
