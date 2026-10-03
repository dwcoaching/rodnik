<?php

declare(strict_types=1);

use App\Actions\Springs\PatchSpringsLocationAction;
use App\Actions\Springs\PostSpringsAction;
use App\Contracts\ReverseGeocoder;
use App\Library\Overpass;
use App\Models\Spring;
use App\Models\SpringTile;
use App\Models\User;
use App\Models\WateredSpringTile;
use App\Services\Geocoding\GeocodedLocation;
use App\Services\Geocoding\GeocodeSpring;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

final class RecordingSpringGeocoder implements ReverseGeocoder
{
    public array $lookups = [];

    public bool $broken = false;

    public ?Closure $duringLookup = null;

    public function validateSource(): void
    {
        if ($this->broken) {
            throw new RuntimeException('Invalid geocoding dataset');
        }
    }

    public function reverse(float $latitude, float $longitude): ?GeocodedLocation
    {
        $this->validateSource();
        $this->lookups[] = [$latitude, $longitude];
        ($this->duringLookup ?? static fn () => null)();

        return $latitude < 50
            ? new GeocodedLocation('Armenia', 'AM', 'Yerevan', 'Армения', 'Yerevan')
            : new GeocodedLocation('Russia', 'RU', 'Moscow', 'Россия', 'Moscow');
    }
}

beforeEach(function () {
    app()->instance(ReverseGeocoder::class, $this->geocoder = new RecordingSpringGeocoder);
    Queue::fake();
    Storage::fake(SpringTile::DISK);
    Storage::fake(WateredSpringTile::DISK);
});

test('a user-created source receives country and city before the response', function () {
    $this->actingAs(User::factory()->create());

    $spring = app(PostSpringsAction::class)(['latitude' => 40.18, 'longitude' => 44.51]);

    expect($spring->fresh()->country_code)->toBe('AM')
        ->and($spring->fresh()->city)->toBe('Yerevan')
        ->and($spring->geocoded_at)->not->toBeNull()
        ->and($this->geocoder->lookups)->toBe([[40.18, 44.51]]);
});

test('location edits replace derived labels and unchanged coordinates do not repeat the lookup', function () {
    $this->actingAs(User::factory()->create());
    $spring = Spring::factory()->create(['latitude' => 40.18, 'longitude' => 44.51]);

    app(PatchSpringsLocationAction::class)($spring, ['latitude' => 55.75, 'longitude' => 37.62]);
    app(PatchSpringsLocationAction::class)($spring->fresh(), ['latitude' => '55.750000', 'longitude' => '37.620000']);
    $spring->fresh()->forceFill(['name' => 'A new name'])->save();

    expect($spring->fresh()->country_code)->toBe('RU')
        ->and($spring->fresh()->city)->toBe('Moscow')
        ->and($this->geocoder->lookups)->toBe([[40.18, 44.51], [55.75, 37.62]]);
});

test('osm node and way creation and movement geocode the effective coordinates', function (string $type) {
    $element = (object) [
        'type' => $type,
        'id' => 993412340,
        'version' => 1,
        'lat' => 40.18,
        'lon' => 44.51,
        'center' => (object) ['lat' => 40.18, 'lon' => 44.51],
        'tags' => (object) ['natural' => 'spring'],
    ];
    Overpass::parse((object) ['elements' => [$element]]);
    $spring = Spring::where('osm_'.$type.'_id', $element->id)->firstOrFail();
    expect($spring->country_code)->toBe('AM')->and($spring->city)->toBe('Yerevan');

    $element->version = 2;
    $element->lat = 55.75;
    $element->lon = 37.62;
    $element->center = (object) ['lat' => 55.75, 'lon' => 37.62];
    Overpass::parse((object) ['elements' => [$element]]);

    expect($spring->fresh()->country_code)->toBe('RU')
        ->and($spring->fresh()->city)->toBe('Moscow')
        ->and($this->geocoder->lookups)->toHaveCount(2);
})->with(['node', 'way']);

test('an osm update preserves geocoding for a location that a user moved', function () {
    $spring = Spring::factory()->create([
        'osm_node_id' => 993412341,
        'latitude' => 55.75, 'longitude' => 37.62,
        'osm_latitude' => 40.18, 'osm_longitude' => 44.51,
        'osm_version' => 1,
    ]);

    Overpass::parse((object) ['elements' => [(object) [
        'type' => 'node', 'id' => 993412341, 'version' => 2,
        'lat' => 41.0, 'lon' => 45.0, 'tags' => (object) ['natural' => 'spring'],
    ]]]);

    expect((float) $spring->fresh()->latitude)->toBe(55.75)
        ->and($spring->fresh()->country_code)->toBe('RU')
        ->and($this->geocoder->lookups)->toHaveCount(1);
});

test('invalid or removed coordinates clear country and city', function () {
    $spring = Spring::factory()->create(['latitude' => 40.18, 'longitude' => 44.51]);
    $spring->latitude = null;
    $spring->save();

    expect($spring->fresh()->country)->toBeNull()
        ->and($spring->fresh()->city)->toBeNull()
        ->and($spring->fresh()->geocoded_at)->toBeNull()
        ->and($this->geocoder->lookups)->toHaveCount(1);
});

test('an unavailable dataset never prevents source creation or retains labels after movement', function () {
    $spring = Spring::factory()->create(['latitude' => 40.18, 'longitude' => 44.51]);
    $this->geocoder->broken = true;
    $spring->latitude = 55.75;
    $spring->save();
    $new = Spring::factory()->create(['latitude' => 0, 'longitude' => 0]);

    expect($spring->fresh()->country)->toBeNull()
        ->and($spring->fresh()->city)->toBeNull()
        ->and($spring->fresh()->geocoded_at)->toBeNull()
        ->and($new->exists)->toBeTrue();
});

test('a background lookup cannot overwrite the result of a concurrent location edit', function () {
    $spring = Spring::factory()->create(['latitude' => 40.18, 'longitude' => 44.51]);
    $this->geocoder->duringLookup = static function () use ($spring): void {
        DB::table('springs')->where('id', $spring->id)->update([
            'latitude' => 55.75, 'longitude' => 37.62, 'country' => 'Russia', 'country_code' => 'RU', 'city' => 'Moscow',
        ]);
    };

    expect(fn () => app(GeocodeSpring::class)->geocode($spring))->toThrow(RuntimeException::class, 'coordinates changed');
    expect($spring->fresh()->country_code)->toBe('RU')->and($spring->fresh()->city)->toBe('Moscow');
});
