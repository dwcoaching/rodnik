<?php

declare(strict_types=1);

use App\Actions\GeocodeSpringAction;
use App\Contracts\ReverseGeocoder;
use App\Models\Spring;
use App\Models\User;
use App\Services\Geocoding\GeocodedLocation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

final class GeocodingActionTestSource implements ReverseGeocoder
{
    public bool $invalid = false;

    public int $validationCalls = 0;

    public int $lookupCalls = 0;

    public function validateSource(): void
    {
        $this->validationCalls++;
        if ($this->invalid) {
            throw new RuntimeException('Invalid test geocoding dataset.');
        }
    }

    public function reverse(float $latitude, float $longitude): ?GeocodedLocation
    {
        $this->lookupCalls++;

        return catalogLocation('RU', 'Moscow', 'test-dataset');
    }
}

test('the manual geocoding action validates the source and persists derived fields for admins', function (array $role) {
    $this->actingAs(User::factory()->create($role));
    $source = new GeocodingActionTestSource();
    $this->instance(ReverseGeocoder::class, $source);
    $spring = Spring::withoutEvents(fn () => Spring::factory()->create([
        'latitude' => 55.75,
        'longitude' => 37.62,
        ...catalogLinks('ZZ', 'Old city'),
    ]));

    expect(app(GeocodeSpringAction::class)($spring))->toBeTrue();

    expect($source->validationCalls)->toBe(1)
        ->and($source->lookupCalls)->toBe(1)
        ->and($spring->fresh()->geoCountry->code)->toBe('RU')
        ->and($spring->fresh()->nearestCity->name)->toBe('Moscow')
        ->and($spring->fresh()->geocoding_source)->toBe('test-dataset')
        ->and($spring->fresh()->geocoded_at)->not->toBeNull();
})->with([
    'admin' => [['is_admin' => true, 'is_superadmin' => false]],
    'superadmin' => [['is_admin' => false, 'is_superadmin' => true]],
]);

test('the manual geocoding action rejects visitors and ordinary users before reading the source', function (bool $signedIn) {
    if ($signedIn) {
        $this->actingAs(User::factory()->create(['is_admin' => false, 'is_superadmin' => false]));
    }
    $source = new GeocodingActionTestSource();
    $this->instance(ReverseGeocoder::class, $source);
    $spring = Spring::withoutEvents(fn () => Spring::factory()->create(catalogLinks('ZZ', 'Old city')));

    expect(fn () => app(GeocodeSpringAction::class)($spring))->toThrow(AuthorizationException::class);

    expect($source->validationCalls)->toBe(0)
        ->and($source->lookupCalls)->toBe(0)
        ->and($spring->fresh()->geoCountry->code)->toBe('ZZ')
        ->and($spring->fresh()->nearestCity->name)->toBe('Old city');
})->with(['visitor' => false, 'ordinary user' => true]);

test('the manual geocoding action preserves existing labels when source validation fails', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $source = new GeocodingActionTestSource();
    $source->invalid = true;
    $this->instance(ReverseGeocoder::class, $source);
    $spring = Spring::withoutEvents(fn () => Spring::factory()->create(catalogLinks('ZZ', 'Old city')));

    expect(fn () => app(GeocodeSpringAction::class)($spring))->toThrow(RuntimeException::class, 'Invalid test geocoding dataset.');

    expect($source->lookupCalls)->toBe(0)
        ->and($spring->fresh()->geoCountry->code)->toBe('ZZ')
        ->and($spring->fresh()->nearestCity->name)->toBe('Old city');
});
