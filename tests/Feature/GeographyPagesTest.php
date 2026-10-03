<?php

declare(strict_types=1);

use App\Models\City;
use App\Models\Country;
use App\Models\Region;
use App\Models\Spring;
use App\Services\Geography\PublicGeography;
use Illuminate\Foundation\Testing\RefreshDatabase;

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

test('geography counts and listings exclude hidden and redirected sources in both locales', function (string $prefix, string $countryName, string $regionName, string $cityName) {
    [$country, $region, $city] = geographyFixtures();
    $visible = geographySpring($country, $region, $city, ['name' => 'Visible location source']);
    geographySpring($country, $region, $city, ['name' => 'Hidden location source', 'hidden_at' => now()]);
    geographySpring($country, $region, $city, ['name' => 'Redirected location source', 'redirect_to_spring_id' => $visible->id]);

    $this->get($prefix.'/countries')->assertOk()->assertSee($countryName)
        ->assertSee('href="'.url($prefix.'/countries/'.$country->slug).'"', false)
        ->assertViewHas('countries', fn ($countries): bool => $countries->count() === 1 && (int) $countries->first()->sources_count === 1);

    foreach (['countries/'.$country->slug, 'regions/'.$region->slug, 'cities/'.$city->slug] as $path) {
        $this->get($prefix.'/'.$path)->assertOk()->assertSee('Visible location source')
            ->assertDontSee('Hidden location source')->assertDontSee('Redirected location source')
            ->assertSee('href="'.app(App\Support\DuoUrl::class)->absolute(['spring' => $visible->id], $prefix === '/ru' ? 'ru' : 'en').'"', false)
            ->assertViewHas('springs', fn ($springs): bool => $springs->total() === 1);
    }

    $this->get($prefix.'/countries/'.$country->slug)->assertOk()->assertSee($regionName)->assertSee($cityName);
})->with([
    'English' => ['', 'Russia', 'Moscow Oblast', 'Moscow'],
    'Russian' => ['/ru', 'Россия', 'Московская область', 'Москва'],
]);

test('nearest settlement pages count foreign sources independently of their country and region', function () {
    [$country, $region, $city] = geographyFixtures();
    $foreignCountry = Country::create(['code' => 'FI', 'name' => 'Finland', 'slug' => 'finland-fi', 'is_active' => true]);
    $foreignRegion = Region::create(['source_key' => 'geonames-admin1:FI.01', 'source_country_code' => 'FI', 'admin1_code' => '01', 'country_id' => $foreignCountry->id, 'name' => 'Foreign region', 'slug' => 'foreign-region-test', 'is_active' => true]);
    geographySpring($foreignCountry, $foreignRegion, $city, ['name' => 'Cross-border source']);

    $this->get('/cities/'.$city->slug)->assertOk()->assertSee('Water sources near Moscow')
        ->assertSee('Cross-border source')->assertSee('Finland')
        ->assertSee('across a country border')
        ->assertDontSee('href="'.url('/countries/'.$country->slug).'"', false);
    $this->get('/countries/'.$country->slug)->assertNotFound();
    $this->get('/regions/'.$region->slug)->assertNotFound();
    $this->get('/countries/'.$foreignCountry->slug)->assertOk()->assertSee('Cross-border source');

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

test('geography pages have localized metadata pagination and original-name fallbacks', function () {
    [$country, $region, $city] = geographyFixtures();
    $city->update(['name_ru' => null]);
    for ($i = 1; $i <= 31; $i++) {
        geographySpring($country, $region, $city, ['name' => 'Paginated source '.$i]);
    }

    $this->get('/ru/cities/'.$city->slug.'?page=2&utm_source=test&unknown=value')->assertOk()
        ->assertSee('Источники воды рядом с населённым пунктом Moscow')
        ->assertSee('Paginated source 31')->assertDontSee('>Paginated source 1</a>', false)
        ->assertSee('<link rel="canonical" href="'.url('/ru/cities/'.$city->slug).'?page=2">', false)
        ->assertSee('<link rel="alternate" hreflang="en" href="'.url('/cities/'.$city->slug).'?page=2">', false)
        ->assertSee('<link rel="alternate" hreflang="ru" href="'.url('/ru/cities/'.$city->slug).'?page=2">', false)
        ->assertDontSee('name="robots" content="noindex, nofollow"', false);

    $this->get('/cities/'.$city->slug.'?page=1')->assertOk()
        ->assertSee('<link rel="canonical" href="'.url('/cities/'.$city->slug).'">', false);
    foreach (['page=0', 'page=-1', 'page[]=2', 'page=999', 'settlements_page=2'] as $query) {
        $this->get('/cities/'.$city->slug.'?'.$query)->assertNotFound();
    }
});

test('inactive and empty geographic places are excluded from browsing and sitemaps', function () {
    [$country, $region, $city] = geographyFixtures();
    geographySpring($country, $region, $city);
    $emptyCity = City::create(['source_key' => 'geonames:999901', 'source_country_code' => 'RU', 'geonames_id' => 999901, 'latitude' => 55.76, 'longitude' => 37.63, 'country_id' => $country->id, 'region_id' => $region->id, 'name' => 'Empty settlement', 'slug' => 'empty-settlement-test', 'is_active' => true]);
    $inactiveCity = City::create(['source_key' => 'geonames:999902', 'source_country_code' => 'RU', 'geonames_id' => 999902, 'latitude' => 55.77, 'longitude' => 37.64, 'country_id' => $country->id, 'region_id' => $region->id, 'name' => 'Inactive settlement', 'slug' => 'inactive-settlement-test', 'is_active' => false]);
    geographySpring($country, $region, $inactiveCity);
    $hiddenOnlyCountry = Country::create(['code' => 'DE', 'name' => 'Hidden-only country', 'slug' => 'hidden-only-test', 'is_active' => true]);
    geographySpring($hiddenOnlyCountry, null, null, ['hidden_at' => now()]);

    $this->get('/cities/'.$emptyCity->slug)->assertNotFound();
    $this->get('/cities/'.$inactiveCity->slug)->assertNotFound();
    $this->get('/countries')->assertOk()->assertDontSee('Hidden-only country');
    $this->get('/countries/'.$country->slug)->assertOk()->assertDontSee('Empty settlement')->assertDontSee('Inactive settlement');
    $this->get('/sitemap.xml')->assertOk()
        ->assertSee(route('sitemap.locations', ['type' => 'countries', 'page' => 1]), false)
        ->assertSee(route('sitemap.locations', ['type' => 'regions', 'page' => 1]), false)
        ->assertSee(route('sitemap.locations', ['type' => 'cities', 'page' => 1]), false);
    $this->get('/sitemaps/locations-cities-1.xml')->assertOk()
        ->assertSee('<loc>'.url('/cities/'.$city->slug).'</loc>', false)
        ->assertSee('<loc>'.url('/ru/cities/'.$city->slug).'</loc>', false)
        ->assertSee('hreflang="x-default" href="'.url('/cities/'.$city->slug).'"', false)
        ->assertDontSee($emptyCity->slug)->assertDontSee($inactiveCity->slug);
    $this->get('/sitemaps/locations-countries-1.xml')->assertOk()->assertDontSee($hiddenOnlyCountry->slug);
    $this->get('/sitemaps/locations-cities-2.xml')->assertNotFound();
    $this->get('/sitemaps/locations-cities-0.xml')->assertNotFound();
    $this->get('/sitemaps/locations-invalid-1.xml')->assertNotFound();
});

test('the empty country directory is not indexed or included in the static sitemap', function () {
    $this->get('/countries')->assertOk()
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    $this->get('/sitemaps/static.xml')->assertOk()->assertDontSee('/countries</loc>', false);
});
