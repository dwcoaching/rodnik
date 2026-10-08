<?php

declare(strict_types=1);

use App\Models\City;
use App\Models\Country;
use App\Models\Region;
use App\Models\Report;
use App\Models\Spring;
use App\Services\Geography\PublicGeography;
use App\Support\DuoUrl;
use Dom\HTMLDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

function geographyFixtures(): array
{
    $country = Country::create(['code' => 'RU', 'name' => 'Russia', 'name_ru' => 'Россия', 'slug' => 'russia-ru', 'is_active' => true]);
    $region = Region::create(['source_key' => 'geonames-admin1:RU.47', 'source_country_code' => 'RU', 'admin1_code' => '47', 'country_id' => $country->id, 'name' => 'Moscow Oblast', 'name_ru' => 'Московская область', 'slug' => 'moscow-oblast-524925', 'is_active' => true]);
    $city = City::create(['source_key' => 'geonames:524901', 'source_country_code' => 'RU', 'geonames_id' => 524901, 'country_id' => $country->id, 'region_id' => $region->id, 'name' => 'Moscow', 'name_ru' => 'Москва', 'slug' => 'moscow-524901', 'latitude' => 55.75, 'longitude' => 37.62, 'is_active' => true]);

    return [$country, $region, $city];
}

function geographySpring(Country $country, ?Region $region, ?City $city, array $attributes = []): Spring
{
    return Spring::withoutEvents(fn (): Spring => Spring::factory()->create(array_merge([
        'country_id' => $country->id,
        'region_id' => $region?->id,
        'nearest_city_id' => $city?->id,
        'nearest_city_distance_km' => $city === null ? null : 2.125,
        'latitude' => 55.75,
        'longitude' => 37.62,
    ], $attributes)));
}

function geographyCountry(string $code, string $name, ?string $nameRu = null): Country
{
    return Country::create(['code' => $code, 'name' => $name, 'name_ru' => $nameRu, 'slug' => Str::slug($name).'-'.Str::lower($code), 'is_active' => true]);
}

function geographyRegion(Country $country, string $name, ?string $nameRu = null): Region
{
    $key = Str::slug($name);

    return Region::create(['source_key' => 'test-region:'.$key, 'source_country_code' => $country->code, 'country_id' => $country->id, 'name' => $name, 'name_ru' => $nameRu, 'slug' => $key.'-test', 'is_active' => true]);
}

function geographyCity(Country $country, ?Region $region, string $name, ?string $nameRu = null): City
{
    $id = City::query()->max('geonames_id') + 1;

    return City::create(['source_key' => 'geonames:'.$id, 'source_country_code' => $country->code, 'geonames_id' => $id, 'country_id' => $country->id, 'region_id' => $region?->id, 'name' => $name, 'name_ru' => $nameRu, 'slug' => Str::slug($name).'-'.$id, 'latitude' => 55.7, 'longitude' => 37.6, 'is_active' => true]);
}

/** @return list<string> */
function geographyTexts(TestResponse $response, string $selector): array
{
    $document = HTMLDocument::createFromString($response->getContent(), LIBXML_NOERROR);

    return array_map(
        fn ($node): string => mb_trim((string) preg_replace('/\s+/u', ' ', $node->textContent)),
        iterator_to_array($document->querySelectorAll($selector)),
    );
}

function geographyDocument(TestResponse $response): HTMLDocument
{
    return HTMLDocument::createFromString($response->getContent(), LIBXML_NOERROR);
}

test('location pages lead from countries through regions and settlements to water sources in both locales', function (string $prefix, array $names) {
    [$country, $region, $moscow] = geographyFixtures();
    $tverRegion = geographyRegion($country, 'Tver Oblast', 'Тверская область');
    $klin = geographyCity($country, $region, 'Klin', 'Клин');
    $tver = geographyCity($country, $tverRegion, 'Tver', 'Тверь');
    $outside = geographyCity($country, null, 'Baikonur', 'Байконур');
    $near = geographySpring($country, $region, $moscow, ['name' => 'Near source', 'type' => 'Spring', 'nearest_city_distance_km' => 0.4]);
    geographySpring($country, $region, $moscow, ['name' => 'Far source', 'nearest_city_distance_km' => 3.25, 'type' => 'Water well']);
    geographySpring($country, $region, $moscow, ['name' => null, 'type' => 'Fountain', 'nearest_city_distance_km' => 1.5]);
    geographySpring($country, $region, $moscow, ['name' => 'Hidden location source', 'hidden_at' => now()]);
    geographySpring($country, $region, $moscow, ['name' => 'Redirected location source', 'redirect_to_spring_id' => $near->id]);
    geographySpring($country, $region, $klin, ['name' => 'Klin source']);
    geographySpring($country, $tverRegion, $tver, ['name' => 'Tver source']);
    geographySpring($country, $tverRegion, $tver, ['name' => 'Second Tver source']);
    geographySpring($country, null, $outside, ['name' => 'Outside source']);
    $locale = $prefix === '' ? 'en' : 'ru';
    $url = fn (string $path): string => url($prefix.'/docs/'.$path);

    $index = $this->get($prefix.'/docs/countries')->assertOk()
        ->assertSee('href="'.$url('countries/'.$country->slug).'"', false)
        ->assertViewHas('countries', fn ($countries): bool => $countries->count() === 1 && (int) $countries->first()->sources_count === 7);
    expect(geographyTexts($index, 'main h2'))->toBe([$names['initial']])
        ->and(geographyTexts($index, 'main ul li'))->toBe([$names['country'].'7']);

    $countryPage = $this->get($prefix.'/docs/countries/'.$country->slug)->assertOk()
        ->assertSee(trans_choice('geography.sources_count', 7, ['count' => 7], $locale))
        ->assertSee('href="'.$url('regions/'.$region->slug).'"', false)
        ->assertSee('href="'.$url('cities/'.$outside->slug).'"', false)
        ->assertSee(trans('geography.outside_regions', locale: $locale))
        ->assertDontSee('Near source')->assertDontSee('Tver source')
        ->assertViewHas('regions', fn ($regions): bool => $regions->pluck('sources_count', 'id')->map(fn ($count): int => (int) $count)->all() === [$region->id => 4, $tverRegion->id => 2]);
    expect(geographyTexts($countryPage, 'section[aria-labelledby="geography-regions"] li'))->toBe($names['regions'])
        ->and(geographyTexts($countryPage, 'section[aria-labelledby="geography-settlements"] li'))->toBe([$names['outside'].'1'])
        ->and(geographyTexts($countryPage, 'section[aria-labelledby="geography-top-settlements"] li'))->toBe($names['top'])
        ->and(geographyTexts($countryPage, 'nav[aria-label="'.trans('geography.breadcrumbs', locale: $locale).'"] li'))
        ->toBe([trans('geography.docs', locale: $locale), '› '.trans('geography.all_countries', locale: $locale)]);

    $regionPage = $this->get($prefix.'/docs/regions/'.$region->slug)->assertOk()
        ->assertSee(trans_choice('geography.sources_count', 4, ['count' => 4], $locale))
        ->assertSee('href="'.$url('cities/'.$klin->slug).'"', false)
        ->assertSee('href="'.$url('countries/'.$country->slug).'"', false)
        ->assertDontSee('Near source')->assertDontSee('Klin source')->assertDontSee($names['outside']);
    expect(geographyTexts($regionPage, 'section[aria-labelledby="geography-settlements"] li'))->toBe($names['settlements']);

    $cityPage = $this->get($prefix.'/docs/cities/'.$moscow->slug)->assertOk()
        ->assertDontSee('Hidden location source')->assertDontSee('Redirected location source')
        ->assertSee('href="'.app(DuoUrl::class)->absolute(['spring' => $near->id], $locale).'"', false)
        ->assertSee('href="'.$url('regions/'.$region->slug).'"', false)
        ->assertViewHas('springs', fn ($springs): bool => $springs->total() === 3 && ! $springs->hasPages());
    expect(geographyTexts($cityPage, 'section[aria-labelledby="geography-sources"] li > a'))
        ->toBe(['Near source', trans('ui.spring.types.fountain', locale: $locale), 'Far source'])
        ->and(geographyTexts($cityPage, 'section[aria-labelledby="geography-sources"] li > span'))
        ->toBe([trans('ui.spring.types.spring', locale: $locale), trans('ui.spring.types.water_well', locale: $locale)])
        ->and(geographyTexts($cityPage, 'section[aria-labelledby="geography-sources"] li > small'))
        ->toBe(array_map(fn (string $distance): string => trans('geography.distance', ['distance' => $distance], $locale).' · 55.75000, 37.62000', $names['distances']))
        ->and(geographyTexts($cityPage, 'nav[aria-label="'.trans('geography.breadcrumbs', locale: $locale).'"] a'))
        ->toBe([trans('geography.docs', locale: $locale), trans('geography.all_countries', locale: $locale), $names['country'], $names['region']])
        ->and(geographyDocument($cityPage)->querySelector('nav[aria-label="'.trans('geography.breadcrumbs', locale: $locale).'"] a')->getAttribute('href'))
        ->toBe(url($prefix.'/docs'));
})->with([
    'English' => ['', [
        'initial' => 'R', 'country' => 'Russia', 'region' => 'Moscow Oblast', 'outside' => 'Baikonur',
        'regions' => ['Moscow Oblast4', 'Tver Oblast2'],
        'top' => ['Moscow3', 'Tver2', 'Klin1', 'Baikonur1'],
        'settlements' => ['Klin1', 'Moscow3'],
        'distances' => ['0.4', '1.5', '3.3'],
    ]],
    'Russian' => ['/ru', [
        'initial' => 'Р', 'country' => 'Россия', 'region' => 'Московская область', 'outside' => 'Байконур',
        'regions' => ['Московская область4', 'Тверская область2'],
        'top' => ['Москва3', 'Тверь2', 'Клин1', 'Байконур1'],
        'settlements' => ['Клин1', 'Москва3'],
        'distances' => ['0,4', '1,5', '3,3'],
    ]],
]);

test('location lists are alphabetical in the current locale and grouped by initial letter', function (string $prefix, array $order, array $letters) {
    foreach ([
        ['AX', 'Åland Islands', 'Аландские острова'], ['AL', 'Albania', 'Албания'], ['BE', 'Belgium', 'Бельгия'],
        ['ZM', 'Zambia', 'Замбия'], ['YE', 'Yemen', 'Йемен'], ['EE', 'Estonia', 'Эстония'],
        ['XE', 'Eland', 'Ёлкия'], ['XI', 'Iland', 'Иландия'],
    ] as [$code, $name, $nameRu]) {
        geographySpring(geographyCountry($code, $name, $nameRu), null, null);
    }

    $response = $this->get($prefix.'/docs/countries')->assertOk();
    $document = geographyDocument($response);
    $navigation = geographyTexts($response, 'nav[aria-label="'.trans('geography.letters', locale: $prefix === '' ? 'en' : 'ru').'"] a');

    expect(geographyTexts($response, 'main ul li > a'))->toBe($order)
        ->and(geographyTexts($response, 'main h2'))->toBe($letters)
        ->and($navigation)->toBe($letters)
        ->and($document->querySelector('nav a[href="#countries-2"]')->hasAttribute('data-rodnik-exact-url'))->toBeTrue()
        ->and($document->getElementById('countries-2')->textContent)->toBe($letters[1]);
})->with([
    'English' => ['', ['Åland Islands', 'Albania', 'Belgium', 'Eland', 'Estonia', 'Iland', 'Yemen', 'Zambia'], ['A', 'B', 'E', 'I', 'Y', 'Z']],
    'Russian' => ['/ru', ['Аландские острова', 'Албания', 'Бельгия', 'Ёлкия', 'Замбия', 'Иландия', 'Йемен', 'Эстония'], ['А', 'Б', 'Е', 'З', 'И', 'Й', 'Э']],
]);

test('country and region pages list the most reported visible sources from visible reports', function () {
    [$country, $region, $city] = geographyFixtures();
    $otherRegion = geographyRegion($country, 'Tver Oblast');
    $foreign = geographyCountry('FI', 'Finland');
    $report = fn (Spring $spring, int $count = 1, array $attributes = []) => Report::factory()->count($count)->for($spring)->create($attributes);
    $top = geographySpring($country, $region, $city, ['name' => 'Most reported source', 'type' => 'Spring']);
    $report($top, 3);
    $report($top, 1, ['hidden_at' => now()]);
    $report($top, 2, ['from_osm' => true]);
    $hidden = geographySpring($country, $region, $city, ['name' => 'Hidden reported source', 'hidden_at' => now()]);
    $report($hidden, 5);
    $abroad = geographySpring($foreign, null, $city, ['name' => 'Foreign reported source']);
    $report($abroad, 4);
    $onlyHiddenReports = geographySpring($country, $region, $city, ['name' => 'Source with hidden reports']);
    $report($onlyHiddenReports, 2, ['hidden_at' => now()]);
    $older = geographySpring($country, $region, $city, ['name' => 'Earlier reported source']);
    $report($older);
    $elsewhere = geographySpring($country, $otherRegion, null, ['name' => 'Other region source', 'type' => 'Water tap']);
    $report($elsewhere, 2);
    $recent = geographySpring($country, $region, $city, ['name' => null, 'type' => 'Fountain']);
    $report($recent);
    $filler = collect(range(1, 8))->map(function (int $number) use ($country, $region, $report): Spring {
        $spring = geographySpring($country, $region, null, ['name' => 'Filler source '.$number]);
        $report($spring);

        return $spring;
    });

    $countryIds = [$top->id, $elsewhere->id, ...$filler->reverse()->pluck('id')->all(), $recent->id];
    $this->get('/docs/countries/'.$country->slug)->assertOk()
        ->assertViewHas('mostReported', fn ($springs): bool => $springs->pluck('id')->all() === array_slice($countryIds, 0, 10))
        ->assertSee('Most reported water sources')
        ->assertSee('3 reports')->assertSee('2 reports')
        ->assertDontSee('Hidden reported source')->assertDontSee('Foreign reported source')
        ->assertDontSee('Source with hidden reports')->assertDontSee('Earlier reported source');

    $regionPage = $this->get('/ru/docs/regions/'.$region->slug)->assertOk()
        ->assertViewHas('mostReported', fn ($springs): bool => $springs->pluck('id')->all() === [$top->id, ...$filler->reverse()->pluck('id')->all(), $recent->id])
        ->assertSee('Источники с наибольшим числом отчётов')
        ->assertSee('3 отчёта')
        ->assertDontSee('Other region source');
    expect(geographyTexts($regionPage, 'section[aria-labelledby="geography-most-reported"] li')[0])
        ->toBe('Most reported source Родник · Москва 3 отчёта')
        ->and(geographyTexts($regionPage, 'section[aria-labelledby="geography-most-reported"] li > a:first-child')[9])->toBe('Фонтан');

    $this->get('/docs/countries/'.$foreign->slug)->assertOk()->assertSee('Foreign reported source')->assertSee('4 reports');
});

test('nearest settlement pages count foreign sources independently of their country and region', function () {
    [$country, $region, $city] = geographyFixtures();
    $foreignCountry = Country::create(['code' => 'FI', 'name' => 'Finland', 'slug' => 'finland-fi', 'is_active' => true]);
    $foreignRegion = Region::create(['source_key' => 'geonames-admin1:FI.01', 'source_country_code' => 'FI', 'admin1_code' => '01', 'country_id' => $foreignCountry->id, 'name' => 'Foreign region', 'slug' => 'foreign-region-test', 'is_active' => true]);
    geographySpring($foreignCountry, $foreignRegion, $city, ['name' => 'Cross-border source']);

    $this->get('/docs/cities/'.$city->slug)->assertOk()->assertSee('Water sources near Moscow')
        ->assertSee('Cross-border source')->assertSee('Finland')
        ->assertSee('across a country border')
        ->assertDontSee('href="'.url('/docs/countries/'.$country->slug).'"', false);
    $this->get('/docs/countries/'.$country->slug)->assertNotFound();
    $this->get('/docs/regions/'.$region->slug)->assertNotFound();
    $this->get('/docs/countries/'.$foreignCountry->slug)->assertOk()->assertSee('1 water source')
        ->assertSee('href="'.url('/docs/regions/'.$foreignRegion->slug).'"', false)
        ->assertDontSee('Moscow')
        ->assertViewHas('settlements', fn ($settlements): bool => $settlements->total() === 0);
    $this->get('/docs/regions/'.$foreignRegion->slug)->assertOk()->assertSee('1 water source')->assertDontSee('Moscow');

    expect(app(PublicGeography::class)->springsFor($city)->count())->toBe(1)
        ->and(app(PublicGeography::class)->springsFor($country)->count())->toBe(0)
        ->and(app(PublicGeography::class)->springsFor($foreignRegion)->count())->toBe(1);

    $countryCities = app(PublicGeography::class)->cities($country)->get();
    $regionCities = app(PublicGeography::class)->cities(region: $region)->get();
    expect($countryCities)->toHaveCount(1)
        ->and($countryCities->first()->id)->toBe($city->id)
        ->and((int) $countryCities->first()->sources_count)->toBe(1)
        ->and($regionCities)->toHaveCount(1)
        ->and($regionCities->first()->id)->toBe($city->id)
        ->and((int) $regionCities->first()->sources_count)->toBe(1);
});

test('location pages without pagination redirect page parameters to their clean URL', function (string $prefix) {
    [$country, $region, $city] = geographyFixtures();
    geographySpring($country, $region, $city);
    geographySpring($country, null, geographyCity($country, null, 'Baikonur'));

    foreach (['countries', 'countries/'.$country->slug, 'regions/'.$region->slug, 'cities/'.$city->slug] as $path) {
        $clean = url($prefix.'/docs/'.$path);
        foreach (['page=2', 'page=1', 'page=0', 'page=999', 'page[]=2', 'page=', 'settlements_page=2', 'settlements_page=1&page=3'] as $query) {
            $this->get($prefix.'/docs/'.$path.'?'.$query)->assertMovedPermanently()->assertRedirect($clean);
        }
        $this->get($prefix.'/docs/'.$path.'?utm_source=news&page=2&ref=mail')->assertMovedPermanently()
            ->assertRedirect($clean.'?utm_source=news&ref=mail');
        $this->get($prefix.'/docs/'.$path.'?utm_source=news&unknown=value')->assertOk()
            ->assertSee('<link rel="canonical" href="'.$clean.'">', false)
            ->assertDontSee('name="robots"', false);
    }
})->with(['English' => '', 'Russian' => '/ru']);

test('lists from the configured length paginate with canonical page URLs', function () {
    config(['geography.list_limit' => 3, 'geography.page_size' => 2]);
    [$country, $region, $city] = geographyFixtures();
    $city->update(['name_ru' => null]);
    foreach ([5, 1, 4, 2, 3] as $distance) {
        geographySpring($country, $region, $city, ['name' => 'Paginated source '.$distance, 'nearest_city_distance_km' => $distance]);
    }
    foreach (['Delta', 'Alpha', 'Charlie'] as $name) {
        geographySpring($country, $region, geographyCity($country, $region, $name.' town'));
        geographySpring($country, null, geographyCity($country, null, $name.' village'));
    }
    $sources = fn (TestResponse $response): array => geographyTexts($response, 'section[aria-labelledby="geography-sources"] li > a');
    $settlements = fn (TestResponse $response): array => geographyTexts($response, 'section[aria-labelledby="geography-settlements"] li > a');

    $first = $this->get('/docs/cities/'.$city->slug)->assertOk()
        ->assertSee('<link rel="canonical" href="'.url('/docs/cities/'.$city->slug).'">', false)
        ->assertSee('<title>Water sources near Moscow — Rodnik.today</title>', false);
    expect($sources($first))->toBe(['Paginated source 1', 'Paginated source 2'])
        ->and(geographyTexts($first, 'nav[data-rodnik-pagination] a'))->toBe(['2', '3', 'Next ›'])
        ->and(geographyDocument($first)->querySelector('nav[data-rodnik-pagination] a[rel="next"]')->getAttribute('href'))
        ->toBe(url('/docs/cities/'.$city->slug).'?page=2');

    $second = $this->get('/ru/docs/cities/'.$city->slug.'?page=2&utm_source=test&unknown=value')->assertOk()
        ->assertSee('<title>Источники воды рядом с населённым пунктом Moscow, страница 2 — Rodnik.today</title>', false)
        ->assertSee('<link rel="canonical" href="'.url('/ru/docs/cities/'.$city->slug).'?page=2">', false)
        ->assertSee('<link rel="alternate" hreflang="en" href="'.url('/docs/cities/'.$city->slug).'?page=2">', false)
        ->assertSee('<link rel="alternate" hreflang="ru" href="'.url('/ru/docs/cities/'.$city->slug).'?page=2">', false)
        ->assertDontSee('name="robots" content="noindex, nofollow"', false);
    expect($sources($second))->toBe(['Paginated source 3', 'Paginated source 4'])
        ->and(geographyDocument($second)->querySelector('nav[data-rodnik-pagination] a[rel="prev"]')->getAttribute('href'))
        ->toBe(url('/ru/docs/cities/'.$city->slug));
    expect($sources($this->get('/docs/cities/'.$city->slug.'?page=3')->assertOk()))->toBe(['Paginated source 5']);

    $this->get('/docs/cities/'.$city->slug.'?page=1')->assertOk()
        ->assertSee('<link rel="canonical" href="'.url('/docs/cities/'.$city->slug).'">', false);
    foreach (['page=0', 'page=-1', 'page[]=2', 'page=abc', 'page=02', 'page=4'] as $query) {
        $this->get('/docs/cities/'.$city->slug.'?'.$query)->assertNotFound();
    }
    $this->get('/docs/cities/'.$city->slug.'?settlements_page=2&page=2')->assertMovedPermanently()
        ->assertRedirect(url('/docs/cities/'.$city->slug).'?page=2');

    expect($settlements($this->get('/docs/regions/'.$region->slug)->assertOk()))->toBe(['Alpha town', 'Charlie town'])
        ->and($settlements($this->get('/docs/regions/'.$region->slug.'?page=2')->assertOk()))->toBe(['Delta town', 'Moscow']);
    $this->get('/docs/regions/'.$region->slug.'?page=3')->assertNotFound();

    $countryPage = $this->get('/docs/countries/'.$country->slug.'?page=2')->assertOk()
        ->assertSee('<link rel="canonical" href="'.url('/docs/countries/'.$country->slug).'?page=2">', false)
        ->assertSee('Moscow Oblast');
    expect($settlements($countryPage))->toBe(['Delta village'])
        ->and($settlements($this->get('/docs/countries/'.$country->slug)->assertOk()))->toBe(['Alpha village', 'Charlie village']);
    $this->get('/docs/countries/'.$country->slug.'?page=3')->assertNotFound();
    $this->get('/docs/countries?page=2')->assertMovedPermanently()->assertRedirect(url('/docs/countries'));

    config(['geography.list_limit' => 6]);
    $this->get('/docs/cities/'.$city->slug.'?page=2')->assertMovedPermanently()->assertRedirect(url('/docs/cities/'.$city->slug));
    expect($sources($this->get('/docs/cities/'.$city->slug)->assertOk()))->toHaveCount(5);
});

test('place names are escaped once in page titles and descriptions', function () {
    [$country, $region] = geographyFixtures();
    $city = geographyCity($country, $region, "Al'Arad & Sons");
    geographySpring($country, $region, $city);

    $document = geographyDocument($this->get('/docs/cities/'.$city->slug)->assertOk());

    expect($document->querySelector('title')->textContent)->toBe("Water sources near Al'Arad & Sons — Rodnik.today")
        ->and($document->querySelector('meta[property="og:title"]')->getAttribute('content'))->toBe("Water sources near Al'Arad & Sons — Rodnik.today")
        ->and($document->querySelector('meta[name="description"]')->getAttribute('content'))->toContain("is Al'Arad & Sons.");
});

test('inactive and empty geographic places are excluded from browsing and sitemaps', function () {
    [$country, $region, $city] = geographyFixtures();
    geographySpring($country, $region, $city);
    $emptyCity = City::create(['source_key' => 'geonames:999901', 'source_country_code' => 'RU', 'geonames_id' => 999901, 'latitude' => 55.76, 'longitude' => 37.63, 'country_id' => $country->id, 'region_id' => $region->id, 'name' => 'Empty settlement', 'slug' => 'empty-settlement-test', 'is_active' => true]);
    $inactiveCity = City::create(['source_key' => 'geonames:999902', 'source_country_code' => 'RU', 'geonames_id' => 999902, 'latitude' => 55.77, 'longitude' => 37.64, 'country_id' => $country->id, 'region_id' => $region->id, 'name' => 'Inactive settlement', 'slug' => 'inactive-settlement-test', 'is_active' => false]);
    geographySpring($country, $region, $inactiveCity);
    $hiddenOnlyCountry = Country::create(['code' => 'DE', 'name' => 'Hidden-only country', 'slug' => 'hidden-only-test', 'is_active' => true]);
    geographySpring($hiddenOnlyCountry, null, null, ['hidden_at' => now()]);
    $inactiveRegion = geographyRegion($country, 'Inactive region');
    $inactiveRegion->update(['is_active' => false]);
    geographySpring($country, $inactiveRegion, null);

    $this->get('/docs/cities/'.$emptyCity->slug)->assertNotFound();
    $this->get('/docs/cities/'.$inactiveCity->slug)->assertNotFound();
    $this->get('/docs/regions/'.$inactiveRegion->slug)->assertNotFound();
    $this->get('/docs/countries/'.$hiddenOnlyCountry->slug)->assertNotFound();
    $this->get('/docs/countries')->assertOk()->assertDontSee('Hidden-only country');
    $this->get('/docs/countries/'.$country->slug)->assertOk()->assertSee('Moscow Oblast')
        ->assertDontSee('Empty settlement')->assertDontSee('Inactive settlement')->assertDontSee('Inactive region');
    $this->get('/docs/regions/'.$region->slug)->assertOk()->assertSee('Moscow')
        ->assertDontSee('Empty settlement')->assertDontSee('Inactive settlement');
    $this->get('/sitemap.xml')->assertOk()
        ->assertSee(route('sitemap.locations', ['type' => 'countries', 'page' => 1]), false)
        ->assertSee(route('sitemap.locations', ['type' => 'regions', 'page' => 1]), false)
        ->assertSee(route('sitemap.locations', ['type' => 'cities', 'page' => 1]), false);
    $this->get('/sitemaps/locations-cities-1.xml')->assertOk()
        ->assertSee('<loc>'.url('/docs/cities/'.$city->slug).'</loc>', false)
        ->assertSee('<loc>'.url('/ru/docs/cities/'.$city->slug).'</loc>', false)
        ->assertSee('hreflang="x-default" href="'.url('/docs/cities/'.$city->slug).'"', false)
        ->assertDontSee($emptyCity->slug)->assertDontSee($inactiveCity->slug);
    $this->get('/sitemaps/locations-countries-1.xml')->assertOk()->assertDontSee($hiddenOnlyCountry->slug);
    $this->get('/sitemaps/locations-cities-2.xml')->assertNotFound();
    $this->get('/sitemaps/locations-cities-0.xml')->assertNotFound();
    $this->get('/sitemaps/locations-invalid-1.xml')->assertNotFound();
});

test('the empty country directory is not indexed or included in the static sitemap', function () {
    $this->get('/docs/countries')->assertOk()
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
        ->assertSee(trans('geography.empty'));
    $this->get('/sitemaps/static.xml')->assertOk()->assertDontSee('/docs/countries</loc>', false);
});

test('location pages that lived outside docs redirect permanently with their query', function (string $from, string $to) {
    $this->get($from)->assertMovedPermanently()->assertRedirect(url($to));
})->with([
    'index' => ['/countries', '/docs/countries'],
    'Russian index' => ['/ru/countries', '/ru/docs/countries'],
    'country' => ['/countries/russia-ru', '/docs/countries/russia-ru'],
    'region' => ['/ru/regions/moscow-oblast-524925', '/ru/docs/regions/moscow-oblast-524925'],
    // Old pages held 30 sources, so their page numbers are dropped rather than sent to a missing page.
    'city with old page' => ['/cities/moscow-524901?page=150&settlements_page=2', '/docs/cities/moscow-524901'],
    'city with other query' => ['/cities/moscow-524901?page=2&utm_source=chat', '/docs/cities/moscow-524901?utm_source=chat'],
]);

test('country pages also list settlements of regions without a page of their own', function () {
    [$country, $region, $city] = geographyFixtures();
    geographySpring($country, $region, $city);
    $quiet = geographyRegion($country, 'Quiet Region');
    $village = geographyCity($country, $quiet, 'Kobilje');
    // Its only source lies in the other region, so the quiet region has no sources and no page.
    geographySpring($country, $region, $village);

    $this->get('/docs/regions/'.$quiet->slug)->assertNotFound();
    expect(geographyTexts($this->get('/docs/countries/'.$country->slug)->assertOk(), 'section[aria-labelledby="geography-settlements"] li a'))
        ->toBe(['Kobilje']);
});

test('page numbers beyond any list are not found instead of failing', function () {
    // Paginated lists: an unpaginated one redirects any page number to its clean URL instead.
    config(['geography.list_limit' => 1, 'geography.page_size' => 1]);
    [$country, $region, $city] = geographyFixtures();
    geographySpring($country, $region, $city);

    foreach (['/docs/cities/'.$city->slug.'?page=922337203685477581', '/docs/maps?page=922337203685477581',
        '/docs/users?page=922337203685477581', '/ru/docs/maps?page=1000001'] as $url) {
        $this->get($url)->assertNotFound();
    }
    foreach (['/sitemaps/maps-99999999999999999999.xml', '/sitemaps/springs-0.xml', '/sitemaps/locations-cities-1000000.xml'] as $url) {
        $this->get($url)->assertNotFound();
    }
});

test('a water source without a distance does not break its settlement page', function () {
    [$country, $region, $city] = geographyFixtures();
    geographySpring($country, $region, $city, ['name' => 'Measured source']);
    geographySpring($country, $region, $city, ['name' => 'Unmeasured source', 'nearest_city_distance_km' => null]);

    $this->get('/docs/cities/'.$city->slug)->assertOk()->assertSee('Measured source')->assertSee('Unmeasured source');
});
