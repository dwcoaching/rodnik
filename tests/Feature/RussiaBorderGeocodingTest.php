<?php

declare(strict_types=1);

use App\Actions\GeocodeSpringAction;
use App\Contracts\ReverseGeocoder;
use App\Models\City;
use App\Models\Country;
use App\Models\Region;
use App\Models\Spring;
use App\Models\User;
use App\Services\Geocoding\GeographyReverseGeocoder;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->russia = Country::create(['code' => 'RU', 'name' => 'Russia', 'name_ru' => 'Россия', 'slug' => 'russia']);
    $this->ukraine = Country::create(['code' => 'UA', 'name' => 'Ukraine', 'name_ru' => 'Украина', 'slug' => 'ukraine']);
    $this->region = Region::factory()->create(['country_id' => $this->russia->id, 'geonames_id' => 703883, 'name' => 'Republic of Crimea']);
    $this->village = City::factory()->create(['country_id' => $this->ukraine->id, 'geonames_id' => 7001, 'name' => 'Nearest village']);
    $this->borderDirectory = sys_get_temp_dir().'/rodnik-border-feature-'.bin2hex(random_bytes(8));
    File::ensureDirectoryExists($this->borderDirectory);
    $base = [
        'type' => 'FeatureCollection',
        'features' => [
            [
                'type' => 'Feature',
                'properties' => ['country' => 'Ukraine', 'country_ru' => 'Украина', 'country_code' => 'UA'],
                'geometry' => ['type' => 'Polygon', 'coordinates' => [[[0, 0], [20, 0], [20, 20], [0, 20], [0, 0]]]],
            ],
            [
                'type' => 'Feature',
                'properties' => ['city' => 'Nearest village', 'country_code' => 'UA', 'region' => 'Foreign region', 'geonames_id' => 7001],
                'geometry' => ['type' => 'Point', 'coordinates' => [5, 5]],
            ],
        ],
    ];
    $borders = [
        'type' => 'FeatureCollection',
        'features' => [[
            'type' => 'Feature',
            'properties' => ['region' => 'Republic of Crimea'],
            'geometry' => ['type' => 'Polygon', 'coordinates' => [[[0, 0], [10, 0], [10, 10], [0, 10], [0, 0]]]],
        ]],
    ];
    file_put_contents($this->borderDirectory.'/base.geojson', json_encode($base, JSON_THROW_ON_ERROR));
    file_put_contents($this->borderDirectory.'/borders.geojson', json_encode($borders, JSON_THROW_ON_ERROR));
    config([
        'geocoding.path' => $this->borderDirectory.'/base.geojson',
        'geocoding.russia_border_path' => $this->borderDirectory.'/borders.geojson',
        'geocoding.cities_path' => null,
        'geocoding.admin1_path' => null,
        'geocoding.reference_path' => null,
        'geocoding.index_cache_path' => null,
    ]);
});

afterEach(function () {
    File::deleteDirectory($this->borderDirectory);
});

test('the application applies the final country policy to source creation and location edits', function () {
    expect(app(ReverseGeocoder::class))->toBeInstanceOf(GeographyReverseGeocoder::class);
    $spring = Spring::factory()->create(['latitude' => 5, 'longitude' => 5]);

    expect($spring->country_id)->toBe($this->russia->id)
        ->and($spring->nearest_city_id)->toBe($this->village->id)
        ->and($spring->region_id)->toBe($this->region->id);

    $spring->forceFill(['latitude' => 15, 'longitude' => 15])->save();

    expect($spring->fresh()->country_id)->toBe($this->ukraine->id)->and($spring->fresh()->region_id)->toBeNull();
});

test('manual and bulk recalculation use the final country policy over legacy provider links', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $spring = Spring::withoutEvents(fn () => Spring::factory()->create([
        'latitude' => 5, 'longitude' => 5, 'country_id' => $this->ukraine->id,
    ]));
    app(GeocodeSpringAction::class)($spring);
    expect($spring->fresh()->geoCountry->code)->toBe('RU')->and($spring->fresh()->nearestCity->name)->toBe('Nearest village');

    $spring->forceFill(['country_id' => $this->ukraine->id])->saveQuietly();
    $this->artisan('springs:geocode')->assertSuccessful();

    expect($spring->fresh()->geoCountry->code)->toBe('RU')->and($spring->fresh()->geoCountry->name_ru)->toBe('Россия');
    $this->artisan('springs:audit-russia-borders')->expectsOutputToContain('0 mismatched countries')->assertSuccessful();
});

test('the border audit detects a source inside the policy that is not linked to Russia and does not modify sources', function () {
    $spring = Spring::withoutEvents(fn () => Spring::factory()->create([
        'latitude' => 5, 'longitude' => 5, 'country_id' => $this->ukraine->id,
    ]));
    Spring::withoutEvents(fn () => Spring::factory()->create([
        'latitude' => 15, 'longitude' => 15, 'country_id' => $this->ukraine->id,
    ]));
    $before = $spring->fresh()->getAttributes();

    $this->artisan('springs:audit-russia-borders')
        ->expectsOutputToContain('2 water sources; 1 within the Russia border policy; 1 mismatched countries')
        ->expectsOutputToContain((string) $spring->id)->assertFailed();

    expect($spring->fresh()->getAttributes())->toBe($before);
});

test('disabled or broken border files fail the audit and broken policies cannot clear labels on reset', function () {
    $spring = Spring::withoutEvents(fn () => Spring::factory()->create([
        'latitude' => 5, 'longitude' => 5, ...catalogLinks('ZZ', 'Saved city'),
    ]));
    config(['geocoding.russia_border_path' => null]);
    $this->artisan('springs:audit-russia-borders')->expectsOutputToContain('policy is disabled')->assertFailed();
    config(['geocoding.russia_border_path' => $this->borderDirectory.'/missing.geojson']);

    $this->artisan('springs:audit-russia-borders')->assertFailed();
    $this->artisan('springs:geocode', ['--reset' => true])->assertFailed();
    expect($spring->fresh()->geoCountry->code)->toBe('ZZ')->and($spring->fresh()->nearestCity->name)->toBe('Saved city');
});

test('the border audit requires Russia in the catalog and passes once sources link to it', function (): void {
    $spring = Spring::withoutEvents(fn () => Spring::factory()->create([
        'latitude' => 5, 'longitude' => 5, 'country_id' => null,
    ]));
    $this->artisan('springs:audit-russia-borders')->expectsOutputToContain('1 mismatched countries')->assertFailed();
    $spring->forceFill(['country_id' => $this->russia->id])->saveQuietly();
    $this->artisan('springs:audit-russia-borders')->expectsOutputToContain('0 mismatched countries')->assertSuccessful();

    $this->russia->update(['is_active' => false]);
    $this->artisan('springs:audit-russia-borders')->expectsOutputToContain('Russia is missing from the geography catalog')->assertFailed();
});
