<?php

declare(strict_types=1);

use App\Actions\GeocodeSpringAction;
use App\Contracts\ReverseGeocoder;
use App\Livewire\Duo\Springs\Show;
use App\Models\City;
use App\Models\Country;
use App\Models\GeocodingRun;
use App\Models\Region;
use App\Models\Spring;
use App\Models\User;
use App\Services\Geocoding\BulkGeocoding;
use App\Services\Geocoding\DatabaseGeographyCatalog;
use App\Services\Geocoding\GeocodedLocation;
use App\Services\Geocoding\GeographyReverseGeocoder;
use App\Services\Geocoding\RegionBoundaryIndex;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->country = Country::create(['code' => 'AA', 'name' => 'Exampleland', 'name_ru' => 'Примерия', 'slug' => 'exampleland']);
    $this->foreignCountry = Country::create(['code' => 'BB', 'name' => 'Neighbor', 'slug' => 'neighbor']);
    $this->region = Region::create([
        'country_id' => $this->country->id, 'source_key' => 'geonames-admin1:AA.01',
        'source_country_code' => 'AA', 'admin1_code' => '01', 'geonames_id' => 4444,
        'name' => 'Actual region', 'name_ru' => 'Регион источника', 'slug' => 'actual-region',
    ]);
    $this->city = City::create([
        'country_id' => $this->foreignCountry->id, 'source_key' => 'geonames:5555',
        'source_country_code' => 'BB', 'geonames_id' => 5555,
        'name' => 'Nearest town', 'name_ru' => 'Ближний город', 'slug' => 'nearest-town',
        'latitude' => 1, 'longitude' => 1.02,
    ]);
    $path = tempnam(sys_get_temp_dir(), 'rodnik-region-link-');
    file_put_contents($path, json_encode([
        'type' => 'FeatureCollection', 'features' => [[
            'type' => 'Feature',
            'properties' => ['source_key' => 'boundary:actual', 'country_code' => 'AA', 'name' => 'Boundary region', 'geonames_id' => 4444],
            'geometry' => ['type' => 'Polygon', 'coordinates' => [[[0, 0], [2, 0], [2, 2], [0, 2], [0, 0]]]],
        ]],
    ], JSON_THROW_ON_ERROR));
    $this->regionPath = $path;
    config(['geocoding.regions_path' => $path, 'geocoding.index_cache_path' => null]);
    $base = new class implements ReverseGeocoder
    {
        public function validateSource(): void {}

        public function reverse(float $latitude, float $longitude): ?GeocodedLocation
        {
            return $latitude < 5 ? new GeocodedLocation(
                'Provider country', 'AA', 'Provider town', region: 'Foreign town region',
                geonamesId: 5555, nearestCityDistanceKm: 2.223,
            ) : null;
        }
    };
    app()->instance(ReverseGeocoder::class, new GeographyReverseGeocoder(
        $base, new RegionBoundaryIndex(app('config')), new DatabaseGeographyCatalog,
    ));
});

afterEach(function (): void {
    unlink($this->regionPath);
});

test('automatic geocoding links actual country and containing region independently from a foreign nearest settlement', function (): void {
    $spring = Spring::factory()->create(['latitude' => 1, 'longitude' => 1]);

    expect($spring->fresh())->country_id->toBe($this->country->id)
        ->region_id->toBe($this->region->id)
        ->nearest_city_id->toBe($this->city->id)
        ->nearest_city_distance_km->toBe(2.223)
        ->country_ru->toBe('Примерия')
        ->region_ru->toBe('Регион источника')
        ->city_ru->toBe('Ближний город');
    expect($spring->fresh()->geoCountry->id)->not->toBe($spring->fresh()->nearestCity->country_id);
});

test('moving beyond coverage clears every catalog link, translated label, and settlement distance', function (): void {
    $spring = Spring::factory()->create(['latitude' => 1, 'longitude' => 1]);
    $spring->forceFill(['latitude' => 6])->save();

    foreach (['country_id', 'region_id', 'nearest_city_id', 'nearest_city_distance_km', 'country_ru', 'region_ru', 'city_ru'] as $field) {
        expect($spring->fresh()->getAttribute($field))->toBeNull();
    }
});

test('source details and API read updated catalog translations without changing saved identities or URLs', function (): void {
    $spring = Spring::factory()->create(['latitude' => 1, 'longitude' => 1]);
    $this->city->update(['name' => 'Renamed town', 'name_ru' => 'Новое название']);
    app()->setLocale('ru');

    Livewire::test(Show::class, ['springId' => $spring->id])
        ->assertSee('Примерия')->assertSee('Регион источника')->assertSee('Новое название')
        ->assertSee('/ru/cities/nearest-town', false);
    $this->getJson('/api/v1/springs/'.$spring->id)
        ->assertOk()->assertJsonPath('data.country_id', $this->country->id)
        ->assertJsonPath('data.region_id', $this->region->id)
        ->assertJsonPath('data.nearest_city_id', $this->city->id)
        ->assertJsonPath('data.city', 'Renamed town')->assertJsonPath('data.city_ru', 'Новое название')
        ->assertJsonPath('data.nearest_city_distance_km', 2.223);
    expect($this->city->fresh()->slug)->toBe('nearest-town');
});

test('an unrecognized replacement settlement retains text labels and has no incorrect catalog association', function (): void {
    app()->instance(ReverseGeocoder::class, new class implements ReverseGeocoder
    {
        public function validateSource(): void {}

        public function reverse(float $latitude, float $longitude): ?GeocodedLocation
        {
            return new GeocodedLocation('Custom country', 'ZZ', 'Custom settlement', cityRu: 'Новый посёлок');
        }
    });
    $spring = Spring::factory()->create(['latitude' => 1, 'longitude' => 1]);
    expect($spring->fresh())->city->toBe('Custom settlement')->city_ru->toBe('Новый посёлок')
        ->country_id->toBeNull()->region_id->toBeNull()->nearest_city_id->toBeNull();
});

test('admin refresh and queued reset persist all catalog links through the shared geocoding workflow', function (): void {
    Queue::fake();
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $spring = Spring::withoutEvents(fn () => Spring::factory()->create(['latitude' => 1, 'longitude' => 1]));
    app(GeocodeSpringAction::class)($spring);
    expect($spring->fresh()->nearest_city_id)->toBe($this->city->id);
    $this->artisan('springs:geocode', ['--reset' => true, '--queue' => true])->assertSuccessful();

    foreach (['country_id', 'region_id', 'nearest_city_id', 'nearest_city_distance_km', 'country_ru', 'region_ru', 'city_ru'] as $field) {
        expect($spring->fresh()->getAttribute($field))->toBeNull();
    }
    $run = GeocodingRun::latest('id')->firstOrFail();
    app(BulkGeocoding::class)->processChunk($run);
    expect($spring->fresh())->country_id->toBe($this->country->id)->region_id->toBe($this->region->id)
        ->nearest_city_id->toBe($this->city->id)->city_ru->toBe('Ближний город');
});
