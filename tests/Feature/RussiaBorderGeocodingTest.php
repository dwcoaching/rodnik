<?php

declare(strict_types=1);

use App\Actions\GeocodeSpringAction;
use App\Contracts\ReverseGeocoder;
use App\Models\Country;
use App\Models\Spring;
use App\Models\User;
use App\Services\Geocoding\GeographyReverseGeocoder;
use Illuminate\Support\Facades\File;

beforeEach(function () {
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
                'properties' => ['city' => 'Nearest village', 'country_code' => 'UA', 'region' => 'Foreign region'],
                'geometry' => ['type' => 'Point', 'coordinates' => [5, 5]],
            ],
        ],
    ];
    $borders = [
        'type' => 'FeatureCollection',
        'features' => [[
            'type' => 'Feature',
            'properties' => ['region' => 'Policy region'],
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

    expect($spring->country)->toBe('Russia')->and($spring->country_ru)->toBe('Россия')
        ->and($spring->country_code)->toBe('RU')->and($spring->city)->toBe('Nearest village')
        ->and($spring->region)->toBe('Policy region');

    $spring->forceFill(['latitude' => 15, 'longitude' => 15])->save();

    expect($spring->fresh()->country_code)->toBe('UA')->and($spring->fresh()->country)->toBe('Ukraine');
});

test('manual and bulk recalculation use the final country policy over legacy provider labels', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $spring = Spring::withoutEvents(fn () => Spring::factory()->create([
        'latitude' => 5, 'longitude' => 5, 'country' => 'Ukraine', 'country_ru' => 'Украина', 'country_code' => 'UA',
    ]));
    app(GeocodeSpringAction::class)($spring);
    expect($spring->fresh()->country_code)->toBe('RU')->and($spring->fresh()->city)->toBe('Nearest village');

    $spring->forceFill(['country' => 'Ukraine', 'country_ru' => 'Украина', 'country_code' => 'UA'])->saveQuietly();
    $this->artisan('springs:geocode')->assertSuccessful();

    expect($spring->fresh()->country)->toBe('Russia')->and($spring->fresh()->country_ru)->toBe('Россия')
        ->and($spring->fresh()->country_code)->toBe('RU');
    $this->artisan('springs:audit-russia-borders')->expectsOutputToContain('0 mismatched labels')->assertSuccessful();
});

test('the border audit detects inconsistent country fields and does not modify sources', function () {
    $spring = Spring::withoutEvents(fn () => Spring::factory()->create([
        'latitude' => 5, 'longitude' => 5, 'country' => 'Russia', 'country_ru' => 'Украина', 'country_code' => 'RU',
    ]));
    Spring::withoutEvents(fn () => Spring::factory()->create([
        'latitude' => 15, 'longitude' => 15, 'country' => 'Ukraine', 'country_ru' => 'Украина', 'country_code' => 'UA',
    ]));
    $before = $spring->fresh()->getAttributes();

    $this->artisan('springs:audit-russia-borders')
        ->expectsOutputToContain('2 water sources; 1 within the Russia border policy; 1 mismatched labels')
        ->expectsOutputToContain((string) $spring->id)->assertFailed();

    expect($spring->fresh()->getAttributes())->toBe($before);
});

test('disabled or broken border files fail the audit and broken policies cannot clear labels on reset', function () {
    $spring = Spring::withoutEvents(fn () => Spring::factory()->create([
        'latitude' => 5, 'longitude' => 5, 'country' => 'Saved country', 'city' => 'Saved city',
    ]));
    config(['geocoding.russia_border_path' => null]);
    $this->artisan('springs:audit-russia-borders')->expectsOutputToContain('policy is disabled')->assertFailed();
    config(['geocoding.russia_border_path' => $this->borderDirectory.'/missing.geojson']);

    $this->artisan('springs:audit-russia-borders')->assertFailed();
    $this->artisan('springs:geocode', ['--reset' => true])->assertFailed();
    expect($spring->fresh()->country)->toBe('Saved country')->and($spring->fresh()->city)->toBe('Saved city');
});

test('the border audit detects a conflicting country identity even when text labels say Russia', function (): void {
    $russia = Country::create(['code' => 'RU', 'name' => 'Russia', 'name_ru' => 'Россия', 'slug' => 'russia']);
    $other = Country::create(['code' => 'UA', 'name' => 'Ukraine', 'slug' => 'ukraine']);
    $spring = Spring::withoutEvents(fn () => Spring::factory()->create([
        'latitude' => 5, 'longitude' => 5, 'country' => 'Russia', 'country_ru' => 'Россия',
        'country_code' => 'RU', 'country_id' => $other->id,
    ]));
    $this->artisan('springs:audit-russia-borders')->expectsOutputToContain('1 mismatched labels')->assertFailed();
    $spring->forceFill(['country_id' => $russia->id])->saveQuietly();
    $this->artisan('springs:audit-russia-borders')->expectsOutputToContain('0 mismatched labels')->assertSuccessful();
});
