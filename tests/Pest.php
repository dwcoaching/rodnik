<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

uses(
    Tests\TestCase::class,
    // Illuminate\Foundation\Testing\RefreshDatabase::class,
)->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/** A catalog country with this ISO code, created on first use. */
function catalogCountry(string $code, ?string $name = null): App\Models\Country
{
    return App\Models\Country::where('code', $code)->first()
        ?? App\Models\Country::factory()->create(['code' => $code, 'name' => $name ?? $code]);
}

/** A catalog city with this name in the given country, created on first use. */
function catalogCity(string $countryCode, string $name): App\Models\City
{
    $country = catalogCountry($countryCode);

    return App\Models\City::where('country_id', $country->id)->where('name', $name)->first()
        ?? App\Models\City::factory()->create(['country_id' => $country->id, 'source_country_code' => $countryCode, 'name' => $name]);
}

/** A catalog region with this name in the given country, created on first use. */
function catalogRegion(string $countryCode, string $name): App\Models\Region
{
    $country = catalogCountry($countryCode);

    return App\Models\Region::where('country_id', $country->id)->where('name', $name)->first()
        ?? App\Models\Region::factory()->create(['country_id' => $country->id, 'source_country_code' => $countryCode, 'name' => $name]);
}

/** A geocoder result that links to catalog rows, so it is actually persisted on a spring. */
function catalogLocation(string $countryCode, string $city, string $source = 'geojson', ?string $region = null): App\Services\Geocoding\GeocodedLocation
{
    $links = catalogLinks($countryCode, $city, $region);

    return new App\Services\Geocoding\GeocodedLocation(
        catalogCountry($countryCode)->name, $countryCode, $city, region: $region, source: $source,
        countryId: $links['country_id'], regionId: $links['region_id'], nearestCityId: $links['nearest_city_id'],
    );
}

/** Catalog links for a spring, e.g. an "old" location that must be preserved or cleared. */
function catalogLinks(string $countryCode, string $city, ?string $region = null): array
{
    $nearest = catalogCity($countryCode, $city);

    return ['country_id' => $nearest->country_id, 'region_id' => $region === null ? null : catalogRegion($countryCode, $region)->id, 'nearest_city_id' => $nearest->id];
}
