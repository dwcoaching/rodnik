<?php

declare(strict_types=1);

use App\Models\City;
use App\Models\Country;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(DatabaseTruncation::class);

beforeEach(function () {
    // Tests\TestCase still opens a transaction, and InnoDB full-text indexes only see committed rows.
    DB::commit();
});

afterEach(function () {
    // Truncation only runs before tests in this file; leave no committed rows for later files.
    Schema::withoutForeignKeyConstraints(function (): void {
        foreach (['springs', 'cities', 'regions', 'countries'] as $table) {
            DB::table($table)->truncate();
        }
    });
});

function placeSearchFullTextCity(Country $country, string $name, string $nameRu, int $population): City
{
    return City::create([
        'source_key' => 'test:'.Str::uuid(), 'source_country_code' => $country->code, 'country_id' => $country->id,
        'name' => $name, 'name_ru' => $nameRu, 'slug' => Str::slug($name).'-'.Str::random(6),
        'latitude' => 55.0, 'longitude' => 37.0, 'population' => $population, 'is_active' => true,
    ]);
}

/** @return list<string> */
function placeSearchFullTextNames(string $query, string $prefix = ''): array
{
    $results = test()->getJson($prefix.'/search/places?'.http_build_query(['q' => $query]))->assertOk()->json('results');

    return array_map(fn (array $result): string => $result['name'].($result['alt_name'] === null ? '' : ' / '.$result['alt_name']), $results);
}

test('later words in settlement names are found through the full-text index', function () {
    $country = Country::create(['code' => 'RU', 'name' => 'Russia', 'name_ru' => 'Россия', 'slug' => 'russia-ru', 'is_active' => true]);
    placeSearchFullTextCity($country, 'Velikiy Novgorod', 'Великий Новгород', 220_000);
    placeSearchFullTextCity($country, 'Nizhniy Novgorod', 'Нижний Новгород', 1_250_000);
    placeSearchFullTextCity($country, 'Rostov-on-Don', 'Ростов-на-Дону', 1_100_000);
    placeSearchFullTextCity($country, 'Saint Petersburg', 'Санкт-Петербург', 5_350_000);
    placeSearchFullTextCity($country, 'The Hague', 'Гаага', 550_000);

    expect(placeSearchFullTextNames('novgorod'))->toBe(['Nizhniy Novgorod', 'Velikiy Novgorod'])
        ->and(placeSearchFullTextNames('новгород', '/ru'))->toBe(['Нижний Новгород', 'Великий Новгород'])
        ->and(placeSearchFullTextNames('дону'))->toBe(['Rostov-on-Don / Ростов-на-Дону'])
        ->and(placeSearchFullTextNames('Петербург', '/ru'))->toBe(['Санкт-Петербург'])
        ->and(placeSearchFullTextNames('petersburg'))->toBe(['Saint Petersburg'])
        ->and(placeSearchFullTextNames('hague'))->toBe(['The Hague'])
        ->and(placeSearchFullTextNames('hague the'))->toBe(['The Hague'])
        // Every typed word, the short last one too, must start a word of the name, in any order.
        ->and(placeSearchFullTextNames('novgorod ve'))->toBe(['Velikiy Novgorod'])
        ->and(placeSearchFullTextNames('новгород ве', '/ru'))->toBe(['Великий Новгород'])
        ->and(placeSearchFullTextNames('novgorod velik'))->toBe(['Velikiy Novgorod']);
});

test('full-text results must match the short words too', function () {
    $country = Country::create(['code' => 'US', 'name' => 'United States', 'name_ru' => 'США', 'slug' => 'united-states-us', 'is_active' => true]);
    placeSearchFullTextCity($country, 'New York City', 'Нью-Йорк', 8_800_000);
    placeSearchFullTextCity($country, 'New Taipei City', 'Новый Тайбэй', 4_000_000);
    placeSearchFullTextCity($country, 'New Delhi', 'Нью-Дели', 250_000);
    placeSearchFullTextCity($country, 'West New York', 'Уэст-Нью-Йорк', 52_000);

    expect(placeSearchFullTextNames('new yo'))->toBe(['New York City', 'West New York'])
        ->and(placeSearchFullTextNames('york new'))->toBe(['New York City', 'West New York']);
});

test('later words are found even when many names start with them', function () {
    $country = Country::create(['code' => 'US', 'name' => 'United States', 'name_ru' => 'США', 'slug' => 'united-states-us', 'is_active' => true]);
    foreach (range(1, 35) as $index) {
        placeSearchFullTextCity($country, 'Francisco '.$index, 'Франсиско '.$index, 100_000 + $index);
    }
    placeSearchFullTextCity($country, 'San Francisco', 'Сан-Франциско', 870_000);

    expect(placeSearchFullTextNames('francisco')[0])->toBe('San Francisco');
});

test('later words are found without diacritics the collation folds', function () {
    $country = Country::create(['code' => 'PL', 'name' => 'Poland', 'name_ru' => 'Польша', 'slug' => 'poland-pl', 'is_active' => true]);
    placeSearchFullTextCity($country, 'Konstantynów Łódzki', 'Константынув-Лодзки', 18_000);
    placeSearchFullTextCity($country, 'Kąty Wrocławskie', 'Конты-Вроцлавске', 5_400);

    expect(placeSearchFullTextNames('lodzki'))->toBe(['Konstantynów Łódzki'])
        ->and(placeSearchFullTextNames('wroclaw'))->toBe(['Kąty Wrocławskie']);
});

test('keyboard layout correction also uses the full-text index', function () {
    $country = Country::create(['code' => 'RU', 'name' => 'Russia', 'name_ru' => 'Россия', 'slug' => 'russia-ru', 'is_active' => true]);
    placeSearchFullTextCity($country, 'Velikiy Novgorod', 'Великий Новгород', 220_000);

    $response = $this->getJson('/ru/search/places?q=yjdujhjl')->assertOk();

    expect($response->json('corrected'))->toBe('новгород')
        ->and($response->json('results.0.name'))->toBe('Великий Новгород');
});
