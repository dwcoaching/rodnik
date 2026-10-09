<?php

declare(strict_types=1);

use App\Models\City;
use App\Models\Country;
use App\Models\Region;
use App\Services\Geocoding\GeoJsonReverseGeocoder;
use App\Services\Geocoding\RussiaBorderPolicy;
use App\Services\Geography\GeographyBoundsImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

/** @param list<array{0:float,1:float,2:float,3:float}> $boxes */
function boundsFeature(array $properties, array $boxes): array
{
    return ['type' => 'Feature', 'properties' => $properties, 'geometry' => [
        'type' => 'MultiPolygon',
        'coordinates' => array_map(static fn (array $box): array => [[
            [$box[0], $box[1]], [$box[2], $box[1]], [$box[2], $box[3]], [$box[0], $box[3]], [$box[0], $box[1]],
        ]], $boxes),
    ]];
}

function boundsCollection(string $path, array $features): void
{
    file_put_contents($path, json_encode(['type' => 'FeatureCollection', 'features' => $features], JSON_THROW_ON_ERROR));
}

function boundsCountry(string $code, array $attributes = []): Country
{
    return Country::create(['code' => $code, 'name' => "Country {$code}", 'slug' => 'country-'.mb_strtolower($code), 'is_active' => true, ...$attributes]);
}

function boundsRegion(Country $country, string $sourceKey, array $attributes = []): Region
{
    return Region::create([
        'country_id' => $country->id, 'source_key' => $sourceKey, 'source_country_code' => $country->code,
        'name' => $sourceKey, 'slug' => 'region-'.md5($sourceKey), 'is_active' => true, ...$attributes,
    ]);
}

function boundsCity(Country $country, float $latitude, float $longitude, array $attributes = []): City
{
    static $id = 95000;
    $id++;

    return City::create([
        'country_id' => $country->id, 'geonames_id' => $id, 'source_country_code' => $country->code,
        'name' => "Place {$id}", 'slug' => "place-{$id}", 'latitude' => $latitude, 'longitude' => $longitude, 'is_active' => true, ...$attributes,
    ]);
}

beforeEach(function () {
    $this->boundsDirectory = sys_get_temp_dir().'/rodnik-bounds-feature-'.bin2hex(random_bytes(8));
    File::ensureDirectoryExists($this->boundsDirectory);
    $this->boundsPaths = ['countries' => $this->boundsDirectory.'/countries.geojson', 'regions' => $this->boundsDirectory.'/regions.geojson'];
    boundsCollection($this->boundsPaths['countries'], [
        // The overseas part is large but far away; the island is split into a second feature.
        boundsFeature(['country' => 'Mainland', 'country_code' => 'AA'], [[-5, 42, 8, 51], [-54.6, 2.1, -51, 5.8]]),
        boundsFeature(['country' => 'Mainland', 'country_code' => 'AA'], [[8.5, 41.3, 9.6, 43]]),
        boundsFeature(['country' => 'Invalid code', 'country_code' => '-99'], [[0, 0, 1, 1]]),
    ]);
    boundsCollection($this->boundsPaths['regions'], [
        boundsFeature(['source_key' => 'geonames-admin1:AA.01', 'geonames_id' => 91101, 'country_code' => 'AA', 'name' => 'North'], [[0, 48, 4, 51]]),
        boundsFeature(['source_key' => 'geonames-admin1:AA.01', 'geonames_id' => 91101, 'country_code' => 'AA', 'name' => 'North'], [[4, 47, 5, 48]]),
        boundsFeature(['source_key' => 'natural-earth-admin1:AA-2', 'country_code' => 'AA', 'name' => 'Unidentified'], [[1, 43, 2, 44]]),
        // Matched by GeoNames ID, so its provider key is irrelevant.
        boundsFeature(['source_key' => 'geonames-admin1:AA.09', 'geonames_id' => 91109, 'country_code' => 'AA', 'name' => 'Renamed'], [[6, 44, 7, 45]]),
    ]);
});

afterEach(function () {
    File::deleteDirectory($this->boundsDirectory);
});

test('bounds come from polygons, then settlements, and are cleared when neither exists', function () {
    $mainland = boundsCountry('AA');
    $islands = boundsCountry('BB');
    $alias = boundsCountry('CC');
    $empty = boundsCountry('DD', ['bbox_west' => 1, 'bbox_south' => 1, 'bbox_east' => 2, 'bbox_north' => 2]);
    // BB's own settlements cross the antimeridian. Inactive settlements are ignored, and
    // members from other provider countries are used only when none have its own code.
    boundsCity($islands, -17.5, 179.5);
    boundsCity($islands, -16.5, -179.0);
    boundsCity($islands, 40.0, 10.0, ['is_active' => false]);
    boundsCity($islands, 30.0, 20.0, ['source_country_code' => 'ZZ']);
    // CC has no settlements with its own provider code, only members such as French overseas settlements.
    boundsCity($alias, 4.0, -53.0, ['source_country_code' => 'XX']);
    boundsCity($alias, 5.0, -52.0, ['source_country_code' => 'XX']);

    $north = boundsRegion($mainland, 'geonames-region:91101', ['geonames_id' => 91101, 'admin1_code' => '01']);
    $unidentified = boundsRegion($mainland, 'natural-earth-admin1:AA-2');
    $renamed = boundsRegion($mainland, 'geonames-region:91109', ['geonames_id' => 91109, 'admin1_code' => '05']);
    $linked = boundsRegion($mainland, 'geonames-region:91102', ['geonames_id' => 91102, 'admin1_code' => '02']);
    $england = boundsRegion($mainland, 'geonames-region:91103', ['geonames_id' => 91103, 'admin1_code' => 'ENG']);
    $nothing = boundsRegion($mainland, 'geonames-region:91104', ['geonames_id' => 91104, 'admin1_code' => '04', 'bbox_west' => 1, 'bbox_south' => 1, 'bbox_east' => 2, 'bbox_north' => 2]);
    boundsCity($mainland, 45.0, 3.0, ['region_id' => $linked->id, 'admin1_code' => '99']);
    boundsCity($mainland, 46.0, 3.5, ['region_id' => $linked->id, 'admin1_code' => '99']);
    boundsCity($mainland, 51.0, 0.0, ['admin1_code' => 'ENG', 'population' => 5000]);
    boundsCity($mainland, 50.0, -1.5, ['admin1_code' => 'ENG', 'population' => 1000]);
    boundsCity($mainland, 52.0, 1.5, ['admin1_code' => 'ENG', 'population' => 2000]);
    boundsCity($mainland, 0.0, 0.0, ['source_country_code' => 'ZZ', 'admin1_code' => '04']);

    $counts = app(GeographyBoundsImporter::class)->import($this->boundsPaths);

    $box = static fn (Country|Region $place): array => [$place->bbox_west, $place->bbox_south, $place->bbox_east, $place->bbox_north];
    expect($counts)->toBe([
        'countries' => ['polygon' => 1, 'settlements' => 2, 'none' => 1],
        'regions' => ['polygon' => 3, 'settlements' => 2, 'none' => 1],
    ])
        ->and($box($mainland->fresh()))->toEqualWithDelta([-5.0, 41.3, 9.6, 51.0], 1e-7)
        ->and($box($islands->fresh()))->toEqualWithDelta([179.5, -17.5, 181.0, -16.5], 1e-7)
        ->and($box($alias->fresh()))->toEqualWithDelta([-53.0, 4.0, -52.0, 5.0], 1e-7)
        ->and($box($empty->fresh()))->toBe([null, null, null, null])
        ->and($box($north->fresh()))->toEqualWithDelta([0.0, 47.0, 5.0, 51.0], 1e-7)
        ->and($box($unidentified->fresh()))->toEqualWithDelta([1.0, 43.0, 2.0, 44.0], 1e-7)
        ->and($box($renamed->fresh()))->toEqualWithDelta([6.0, 44.0, 7.0, 45.0], 1e-7)
        ->and($box($linked->fresh()))->toEqualWithDelta([3.0, 45.0, 3.5, 46.0], 1e-7)
        ->and($box($england->fresh()))->toEqualWithDelta([-1.5, 50.0, 1.5, 52.0], 1e-7)
        ->and($box($nothing->fresh()))->toBe([null, null, null, null])
        ->and($mainland->fresh()->bbox_west)->toBeFloat();
});

test('countries without a polygon use region polygons with their code, and settlements drop remote outliers', function () {
    $sovereign = boundsCountry('AA');
    $svalbard = boundsCountry('SJ');
    $bonaire = boundsCountry('BQ');
    $vietnam = boundsCountry('VN');
    boundsCollection($this->boundsPaths['regions'], [
        // The provider files Svalbard's polygon under its sovereign country.
        boundsFeature(['source_key' => 'geonames-admin1:AA.21', 'geonames_id' => 91121, 'country_code' => 'AA', 'name' => 'Svalbard'], [[10.5, 76.6, 28.0, 80.5]]),
        boundsFeature(['source_key' => 'geonames-admin1:AA.30', 'geonames_id' => 91130, 'country_code' => 'AA', 'name' => 'Removed'], [[-30.0, 60.0, -20.0, 65.0]]),
    ]);
    boundsRegion($sovereign, 'geonames-region:91121', ['geonames_id' => 91121, 'source_country_code' => 'SJ', 'admin1_code' => '21']);
    boundsRegion($sovereign, 'geonames-region:91130', ['geonames_id' => 91130, 'source_country_code' => 'SJ', 'admin1_code' => '30', 'is_active' => false]);
    $janMayen = boundsRegion($svalbard, 'geonames-region:91122', ['geonames_id' => 91122, 'admin1_code' => '22']);
    // Jan Mayen's station would stretch the country's box across the Greenland Sea.
    boundsCity($svalbard, 78.2233, 15.6469, ['population' => 2060]);
    boundsCity($svalbard, 70.9221, -8.7187, ['population' => 14, 'region_id' => $janMayen->id]);
    // Bonaire, Sint Eustatius, and Saba lie ~7 degrees apart; the most populous island wins.
    boundsCity($bonaire, 12.15, -68.27, ['population' => 3000]);
    boundsCity($bonaire, 12.25, -68.35, ['population' => 500]);
    boundsCity($bonaire, 17.48, -62.98, ['population' => 1000]);
    boundsCity($bonaire, 17.62, -63.24, ['population' => 460]);
    // Da Nang's admin1 code also covers an unpopulated outpost in the Paracels.
    $daNang = boundsRegion($vietnam, 'geonames-region:91148', ['geonames_id' => 91148, 'admin1_code' => '48']);
    boundsCity($vietnam, 16.0678, 108.2208, ['admin1_code' => '48', 'population' => 752493]);
    boundsCity($vietnam, 15.98, 108.25, ['admin1_code' => '48', 'population' => 20000]);
    boundsCity($vietnam, 16.5, 112.725, ['admin1_code' => '48']);
    boundsCity($vietnam, 10.8, 106.6, ['admin1_code' => '20', 'population' => 9000000]);

    $counts = app(GeographyBoundsImporter::class)->import($this->boundsPaths);

    $box = static fn (Country|Region $place): array => [$place->bbox_west, $place->bbox_south, $place->bbox_east, $place->bbox_north];
    expect($counts)->toBe([
        'countries' => ['polygon' => 2, 'settlements' => 2, 'none' => 0],
        'regions' => ['polygon' => 2, 'settlements' => 2, 'none' => 0],
    ])
        ->and($box($svalbard->fresh()))->toEqualWithDelta([10.5, 76.6, 28.0, 80.5], 1e-7)
        ->and($box($bonaire->fresh()))->toEqualWithDelta([-68.35, 12.15, -68.27, 12.25], 1e-7)
        ->and($box($vietnam->fresh()))->toEqualWithDelta([106.6, 10.8, 106.6, 10.8], 1e-7)
        ->and($box($janMayen->fresh()))->toEqualWithDelta([-8.7187, 70.9221, -8.7187, 70.9221], 1e-7)
        ->and($box($daNang->fresh()))->toEqualWithDelta([108.2208, 15.98, 108.25, 16.0678], 1e-7);
});

test('without region geometry every region falls back to its settlements', function () {
    $country = boundsCountry('AA');
    $region = boundsRegion($country, 'geonames-region:91101', ['geonames_id' => 91101, 'admin1_code' => '01']);
    boundsCity($country, 48.0, 2.0, ['region_id' => $region->id]);

    $counts = app(GeographyBoundsImporter::class)->import(['countries' => $this->boundsPaths['countries'], 'regions' => null]);

    expect($counts['regions'])->toBe(['polygon' => 0, 'settlements' => 1, 'none' => 0])
        ->and($region->fresh()->bbox_east)->toBe(2.0);
});

test('the bounds command reads explicit paths and reports counts per source', function () {
    boundsCountry('AA');
    boundsCountry('DD');

    $this->artisan('geocoding:import-bounds', ['--countries' => $this->boundsPaths['countries'], '--regions' => $this->boundsPaths['regions']])
        ->expectsOutputToContain('Map bounds for countries: 1 from polygons, 0 from settlements, 1 without.')
        ->expectsOutputToContain('Map bounds for regions: 0 from polygons, 0 from settlements, 0 without.')
        ->assertSuccessful();
    $this->artisan('geocoding:import-bounds', ['--countries' => $this->boundsDirectory.'/missing.geojson'])
        ->expectsOutputToContain('Could not read bounds input')->assertFailed();
});

test('the catalog import stores bounds for the imported places', function () {
    $columns = array_fill(0, 19, '');
    [$columns[0], $columns[4], $columns[16]] = ['AA', 'Mainland', '91001'];
    file_put_contents($this->boundsDirectory.'/countryInfo.txt', implode("\t", $columns)."\n");
    file_put_contents($this->boundsDirectory.'/russian-names.tsv', "91001\tМатерик\n");
    file_put_contents($this->boundsDirectory.'/admin1.txt', "AA.01\tNorth\tNorth\t91101\n");
    $columns = array_fill(0, 19, '');
    [$columns[0], $columns[1], $columns[4], $columns[5], $columns[6], $columns[7], $columns[8], $columns[10]] = ['91201', 'Town', '49', '2', 'P', 'PPL', 'AA', '01'];
    file_put_contents($this->boundsDirectory.'/cities.txt', implode("\t", $columns)."\n");
    config([
        'geocoding.path' => $this->boundsPaths['countries'], 'geocoding.cities_path' => $this->boundsDirectory.'/cities.txt',
        'geocoding.admin1_path' => $this->boundsDirectory.'/admin1.txt', 'geocoding.regions_path' => $this->boundsPaths['regions'],
        'geocoding.russia_border_path' => null, 'geocoding.reference_path' => null, 'geocoding.index_cache_path' => null,
        'geography.country_info_path' => $this->boundsDirectory.'/countryInfo.txt', 'geography.russian_names_path' => $this->boundsDirectory.'/russian-names.tsv',
    ]);
    app()->forgetInstance(GeoJsonReverseGeocoder::class);
    app()->forgetInstance(RussiaBorderPolicy::class);

    // The catalog always contains Russia, which has no geometry or settlements here.
    $this->artisan('geocoding:import-catalog')
        ->expectsOutputToContain('Map bounds for countries: 1 from polygons, 0 from settlements, 1 without.')
        ->expectsOutputToContain('Map bounds for regions: 3 from polygons, 0 from settlements, 0 without.')
        ->assertSuccessful();

    expect(Country::where('code', 'AA')->value('bbox_east'))->toEqualWithDelta(9.6, 1e-7)
        ->and(Region::where('geonames_id', 91101)->value('bbox_south'))->toEqualWithDelta(47.0, 1e-7);
});
