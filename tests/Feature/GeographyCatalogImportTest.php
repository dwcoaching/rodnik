<?php

declare(strict_types=1);

use App\Models\City;
use App\Models\Country;
use App\Models\GeocodingRun;
use App\Models\Region;
use App\Models\Spring;
use App\Services\Geocoding\GeoJsonReverseGeocoder;
use App\Services\Geocoding\RussiaBorderPolicy;
use App\Services\Geography\GeographyCatalogImporter;
use Illuminate\Support\Facades\File;

function geographyCatalogCity(int $id, string $name, string $code = 'AA', string $admin1 = '01', float $latitude = 5, float $longitude = 5): string
{
    $columns = array_fill(0, 19, '');
    foreach ([0 => (string) $id, 1 => $name, 4 => (string) $latitude, 5 => (string) $longitude, 6 => 'P', 7 => 'PPL', 8 => $code, 10 => $admin1] as $key => $value) {
        $columns[$key] = $value;
    }

    return implode("\t", $columns)."\n";
}

function geographyCatalogCountryInfo(string $code, string $name, int $id): string
{
    $columns = array_fill(0, 19, '');
    $columns[0] = $code;
    $columns[4] = $name;
    $columns[16] = (string) $id;

    return implode("\t", $columns)."\n";
}

beforeEach(function () {
    $this->catalogDirectory = sys_get_temp_dir().'/rodnik-catalog-feature-'.bin2hex(random_bytes(8));
    File::ensureDirectoryExists($this->catalogDirectory);
    $this->catalogPaths = [
        'country_info' => $this->catalogDirectory.'/countryInfo.txt',
        'russian_names' => $this->catalogDirectory.'/russian-names.tsv',
        'countries' => $this->catalogDirectory.'/countries.geojson',
        'admin1' => $this->catalogDirectory.'/admin1.txt',
        'cities' => $this->catalogDirectory.'/cities.txt',
        'regions' => null,
    ];
    file_put_contents($this->catalogPaths['country_info'], geographyCatalogCountryInfo('AA', 'Example country', 91001));
    file_put_contents($this->catalogPaths['russian_names'], "91001\tСтрана\n91002\tРегион\n91003\tГород\n");
    file_put_contents($this->catalogPaths['admin1'], "AA.01\tExample region\tExample region\t91002\n");
    file_put_contents($this->catalogPaths['cities'], geographyCatalogCity(91003, 'Example city').geographyCatalogCity(91004, 'Untranslated town'));
    file_put_contents($this->catalogPaths['countries'], json_encode([
        'type' => 'FeatureCollection', 'features' => [[
            'type' => 'Feature', 'properties' => ['country' => 'Example country', 'country_code' => 'AA', 'country_ru' => 'Страна'],
            'geometry' => ['type' => 'Polygon', 'coordinates' => [[[0, 0], [20, 0], [20, 20], [0, 20], [0, 0]]]],
        ]],
    ], JSON_THROW_ON_ERROR));
    config([
        'geocoding.path' => $this->catalogPaths['countries'], 'geocoding.cities_path' => $this->catalogPaths['cities'],
        'geocoding.admin1_path' => $this->catalogPaths['admin1'], 'geocoding.regions_path' => null,
        'geocoding.russia_border_path' => null, 'geocoding.reference_path' => null, 'geocoding.index_cache_path' => null,
        'geography.country_info_path' => $this->catalogPaths['country_info'], 'geography.russian_names_path' => $this->catalogPaths['russian_names'],
    ]);
    app()->forgetInstance(GeoJsonReverseGeocoder::class);
    app()->forgetInstance(RussiaBorderPolicy::class);
});

afterEach(function () {
    File::deleteDirectory($this->catalogDirectory);
});

test('catalog updates retain database identities and URL slugs while refreshing names and centers', function () {
    $importer = app(GeographyCatalogImporter::class);
    $counts = $importer->import($this->catalogPaths);
    $country = Country::where('code', 'AA')->firstOrFail();
    $region = Region::where('geonames_id', 91002)->firstOrFail();
    $city = City::where('geonames_id', 91003)->firstOrFail();
    expect($counts)->toMatchArray(['cities' => 2, 'cities_ru' => 1])
        ->and($country->displayName('ru'))->toBe('Страна')
        ->and($region->displayName('ru'))->toBe('Регион')
        ->and($city->displayName('ru'))->toBe('Город')
        ->and(City::where('geonames_id', 91004)->firstOrFail()->displayName('ru'))->toBe('Untranslated town');

    file_put_contents($this->catalogPaths['country_info'], geographyCatalogCountryInfo('AA', 'Renamed country', 91001));
    file_put_contents($this->catalogPaths['admin1'], "AA.01\tRenamed region\tRenamed region\t91002\n");
    file_put_contents($this->catalogPaths['cities'], geographyCatalogCity(91003, 'Renamed city', latitude: 6).geographyCatalogCity(91004, 'Untranslated town'));
    $importer->import($this->catalogPaths);
    expect($country->fresh()->id)->toBe($country->id)->and($country->fresh()->slug)->toBe($country->slug)
        ->and($country->fresh()->name)->toBe('Renamed country')
        ->and($region->fresh()->id)->toBe($region->id)->and($region->fresh()->slug)->toBe($region->slug)
        ->and($city->fresh()->id)->toBe($city->id)->and($city->fresh()->slug)->toBe($city->slug)
        ->and($city->fresh()->name)->toBe('Renamed city')->and($city->fresh()->latitude)->toBe(6.0);
});

test('removed settlements become inactive and remain linked to existing sources', function () {
    $importer = app(GeographyCatalogImporter::class);
    $importer->import($this->catalogPaths);
    $city = City::where('geonames_id', 91004)->firstOrFail();
    $spring = Spring::withoutEvents(fn () => Spring::factory()->create(['nearest_city_id' => $city->id]));
    file_put_contents($this->catalogPaths['cities'], geographyCatalogCity(91003, 'Example city'));
    $importer->import($this->catalogPaths);

    expect($city->fresh()->is_active)->toBeFalse()->and($spring->fresh()->nearest_city_id)->toBe($city->id)
        ->and(City::where('geonames_id', 91003)->firstOrFail()->is_active)->toBeTrue();
    file_put_contents($this->catalogPaths['cities'], geographyCatalogCity(91003, 'Example city').geographyCatalogCity(91004, 'Returned town'));
    $importer->import($this->catalogPaths);
    expect($city->fresh()->is_active)->toBeTrue()->and($city->fresh()->slug)->toBe($city->slug);
});

test('failed imports preserve the prior catalog and do not deactivate its records', function () {
    $importer = app(GeographyCatalogImporter::class);
    $importer->import($this->catalogPaths);
    $city = City::where('geonames_id', 91003)->firstOrFail();
    $country = Country::where('code', 'AA')->firstOrFail();
    $originalCity = $city->getAttributes();
    $originalCountry = $country->getAttributes();
    file_put_contents($this->catalogPaths['country_info'], geographyCatalogCountryInfo('AA', 'Should roll back', 91001));
    file_put_contents($this->catalogPaths['cities'], geographyCatalogCity(91003, 'Should roll back')."invalid\n");

    expect(fn () => $importer->import($this->catalogPaths))->toThrow(RuntimeException::class)
        ->and($city->fresh()->getAttributes())->toBe($originalCity)
        ->and($country->fresh()->getAttributes())->toBe($originalCountry);
    unlink($this->catalogPaths['cities']);
    expect(fn () => $importer->import($this->catalogPaths))->toThrow(RuntimeException::class)
        ->and($city->fresh()->is_active)->toBeTrue();
});

test('configured territorial policy normalizes settlement and region ownership while retaining provider identities', function () {
    file_put_contents($this->catalogPaths['country_info'], geographyCatalogCountryInfo('UA', 'Ukraine', 690791).geographyCatalogCountryInfo('RU', 'Russia', 2017370));
    file_put_contents($this->catalogPaths['admin1'], "UA.11\tAutonomous Republic of Crimea\tCrimea\t703883\n");
    file_put_contents($this->catalogPaths['cities'], geographyCatalogCity(91003, 'Simferopol', 'UA', '11'));
    $border = $this->catalogDirectory.'/borders.geojson';
    file_put_contents($border, json_encode([
        'type' => 'FeatureCollection', 'features' => [[
            'type' => 'Feature', 'properties' => ['source_code' => 'UA-43', 'region' => 'Республика Крым'],
            'geometry' => ['type' => 'Polygon', 'coordinates' => [[[0, 0], [10, 0], [10, 10], [0, 10], [0, 0]]]],
        ]],
    ], JSON_THROW_ON_ERROR));
    config(['geocoding.russia_border_path' => $border]);
    app()->forgetInstance(RussiaBorderPolicy::class);
    app(GeographyCatalogImporter::class)->import($this->catalogPaths);
    $city = City::where('geonames_id', 91003)->firstOrFail();
    $region = Region::where('geonames_id', 703883)->firstOrFail();
    $russia = Country::where('code', 'RU')->firstOrFail();

    expect($region->country_id)->toBe($russia->id)->and($region->name_ru)->toBe('Республика Крым')
        ->and($region->source_key)->toBe('geonames-region:703883')
        ->and($city->country_id)->toBe($russia->id)->and($city->region_id)->toBe($region->id)
        ->and($city->source_country_code)->toBe('UA')->and($city->geonames_id)->toBe(91003);
});

test('catalog CLI uses configured paths and reports Russian translation coverage', function () {
    $this->artisan('geocoding:import-catalog')->expectsOutputToContain('2 settlements')
        ->expectsOutputToContain('1 settlements. Original names are used')->assertSuccessful();
});

test('catalog imports and bulk geocoding cannot start over one another', function (): void {
    $run = GeocodingRun::create(['active_key' => 'springs', 'mode' => 'all', 'status' => 'queued', 'max_spring_id' => 0, 'total' => 0]);
    $this->artisan('geocoding:import-catalog')->expectsOutputToContain('Finish or stop the active reverse geocoding run')->assertFailed();
    expect(Country::count())->toBe(0)->and(City::count())->toBe(0);
    $run->delete();

    $lock = fopen(storage_path('app/geography-catalog-import.lock'), 'c');
    flock($lock, LOCK_EX);
    try {
        $this->artisan('springs:geocode', ['--queue' => true])->expectsOutputToContain('catalog import is running')->assertFailed();
        expect(GeocodingRun::count())->toBe(0);
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
});

test('administrative code reassignment preserves region identities and links settlements using the current provider codes', function () {
    file_put_contents($this->catalogPaths['admin1'], "AA.01\tFirst region\tFirst region\t91002\nAA.02\tSecond region\tSecond region\t91005\n");
    $importer = app(GeographyCatalogImporter::class);
    $importer->import($this->catalogPaths);
    $first = Region::where('geonames_id', 91002)->firstOrFail();
    $second = Region::where('geonames_id', 91005)->firstOrFail();
    file_put_contents($this->catalogPaths['admin1'], "AA.01\tSecond renamed\tSecond renamed\t91005\nAA.02\tFirst renamed\tFirst renamed\t91002\n");
    $importer->import($this->catalogPaths);

    expect($first->fresh()->id)->toBe($first->id)->and($first->fresh()->slug)->toBe($first->slug)
        ->and($first->fresh()->admin1_code)->toBe('02')
        ->and($second->fresh()->id)->toBe($second->id)->and($second->fresh()->slug)->toBe($second->slug)
        ->and($second->fresh()->admin1_code)->toBe('01')
        ->and(City::where('geonames_id', 91003)->firstOrFail()->region_id)->toBe($second->id);
});

test('an administrative code reused by a new region leaves the former identity inactive and linked records intact', function () {
    $importer = app(GeographyCatalogImporter::class);
    $importer->import($this->catalogPaths);
    $former = Region::where('geonames_id', 91002)->firstOrFail();
    $spring = Spring::withoutEvents(fn () => Spring::factory()->create(['region_id' => $former->id]));
    file_put_contents($this->catalogPaths['admin1'], "AA.01\tNew region\tNew region\t91005\n");
    $importer->import($this->catalogPaths);
    $new = Region::where('geonames_id', 91005)->firstOrFail();

    expect($former->fresh()->is_active)->toBeFalse()->and($spring->fresh()->region_id)->toBe($former->id)
        ->and($new->id)->not->toBe($former->id)
        ->and(City::where('geonames_id', 91003)->firstOrFail()->region_id)->toBe($new->id);
});

test('explicit territorial aliases align country and region links while retaining raw GeoNames country and admin codes', function () {
    file_put_contents($this->catalogPaths['country_info'], geographyCatalogCountryInfo('NL', 'Netherlands', 91001).geographyCatalogCountryInfo('BQ', 'Caribbean Netherlands', 91006));
    file_put_contents($this->catalogPaths['admin1'], "BQ.BO\tBonaire\tBonaire\t91002\n");
    file_put_contents($this->catalogPaths['cities'], geographyCatalogCity(91003, 'Kralendijk', 'BQ', 'BO'));
    $this->catalogPaths['regions'] = $this->catalogDirectory.'/regions.geojson';
    file_put_contents($this->catalogPaths['regions'], json_encode([
        'type' => 'FeatureCollection', 'features' => [[
            'type' => 'Feature',
            'properties' => ['source_key' => 'geonames-admin1:BQ.BO', 'geonames_id' => 91002, 'country_code' => 'NL', 'admin1_code' => 'BO', 'name' => 'Bonaire'],
            'geometry' => ['type' => 'Polygon', 'coordinates' => [[[0, 0], [10, 0], [10, 10], [0, 10], [0, 0]]]],
        ]],
    ], JSON_THROW_ON_ERROR));
    config(['geocoding.city_country_aliases' => ['NL' => ['BQ']]]);
    $importer = app(GeographyCatalogImporter::class);
    $importer->import($this->catalogPaths);
    $city = City::where('geonames_id', 91003)->firstOrFail();
    $region = Region::where('geonames_id', 91002)->firstOrFail();
    $country = Country::where('code', 'NL')->firstOrFail();

    expect($region->country_id)->toBe($country->id)->and($region->source_country_code)->toBe('BQ')
        ->and($city->country_id)->toBe($country->id)->and($city->region_id)->toBe($region->id)
        ->and($city->source_country_code)->toBe('BQ')->and($city->admin1_code)->toBe('BO');
    config(['geocoding.city_country_aliases' => []]);
    $importer->import($this->catalogPaths);
    expect($city->fresh()->country_id)->toBe(Country::where('code', 'BQ')->firstOrFail()->id)
        ->and($city->fresh()->region_id)->toBeNull()
        ->and($region->fresh()->country_id)->toBe($country->id);
});
