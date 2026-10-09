<?php

declare(strict_types=1);

use App\Contracts\ReverseGeocoder;
use App\Http\Resources\SpringResource;
use App\Livewire\Duo\Springs\Show;
use App\Models\Spring;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

beforeEach(function () {
    catalogCountry('RU', 'Russia')->update(['name_ru' => 'Россия']);
    catalogCountry('ZZ', 'Old country');
});

function createGeocodingUiSpring(array $attributes = []): Spring
{
    return Spring::withoutEvents(fn () => Spring::factory()->create(array_merge([
        'latitude' => 55.75,
        'longitude' => 37.62,
    ], $attributes)));
}

test('admins and superadmins can refresh country and city from the source menu', function (array $role) {
    $this->actingAs(User::factory()->create($role));
    $spring = createGeocodingUiSpring(catalogLinks('ZZ', 'Old city'));

    $this->mock(ReverseGeocoder::class, function (MockInterface $mock) {
        $mock->shouldReceive('validateSource')->once();
        $mock->shouldReceive('reverse')->once()->with(55.75, 37.62)
            ->andReturn(catalogLocation('RU', 'Moscow'));
    });

    Livewire::test(Show::class, ['springId' => $spring->id])
        ->assertSee('Refresh country and city')
        ->call('geocode')
        ->assertHasNoErrors()
        ->assertSee('Country and nearest city updated.')
        ->assertSee('Russia')
        ->assertSee('Moscow')
        ->assertDontSee('Old city');

    expect($spring->fresh()->nearestCity->name)->toBe('Moscow');
})->with([
    'admin' => [['is_admin' => true, 'is_superadmin' => false]],
    'superadmin' => [['is_admin' => false, 'is_superadmin' => true]],
]);

test('visitors and ordinary users cannot call the manual geocoding action', function (bool $signedIn) {
    if ($signedIn) {
        $this->actingAs(User::factory()->create(['is_admin' => false, 'is_superadmin' => false]));
    }

    $spring = createGeocodingUiSpring(catalogLinks('RU', 'Moscow'));
    $this->mock(ReverseGeocoder::class, function (MockInterface $mock) {
        $mock->shouldNotReceive('validateSource');
        $mock->shouldNotReceive('reverse');
    });

    Livewire::test(Show::class, ['springId' => $spring->id])
        ->assertDontSee('Refresh country and city')
        ->assertSee('Russia')
        ->assertSee('Moscow')
        ->call('geocode')
        ->assertForbidden();
})->with(['visitor' => false, 'ordinary user' => true]);

test('a geocoding dataset failure shows a safe error and preserves stored labels', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $spring = createGeocodingUiSpring(catalogLinks('RU', 'Moscow'));
    $this->mock(ReverseGeocoder::class, function (MockInterface $mock) {
        $mock->shouldReceive('validateSource')->once()
            ->andThrow(new RuntimeException('Unreadable file /private/internal/countries.json'));
        $mock->shouldNotReceive('reverse');
    });

    Livewire::test(Show::class, ['springId' => $spring->id])
        ->call('geocode')
        ->assertHasErrors('geocoding')
        ->assertSee('Could not update country and city. Check the geocoding dataset and try again.')
        ->assertDontSee('/private/internal/countries.json')
        ->assertSee('Russia')
        ->assertSee('Moscow');

    expect($spring->fresh()->geoCountry->code)->toBe('RU')
        ->and($spring->fresh()->nearestCity->name)->toBe('Moscow');
});

test('a manual geocoding lookup without a match reports that outcome', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $spring = createGeocodingUiSpring(catalogLinks('ZZ', 'Old city'));
    $this->mock(ReverseGeocoder::class, function (MockInterface $mock) {
        $mock->shouldReceive('validateSource')->once();
        $mock->shouldReceive('reverse')->once()->with(55.75, 37.62)->andReturnNull();
    });

    Livewire::test(Show::class, ['springId' => $spring->id])
        ->call('geocode')
        ->assertHasNoErrors()
        ->assertSee('No matching country or city was found.')
        ->assertDontSee('Country and nearest city updated.')
        ->assertDontSee('Old country')
        ->assertDontSee('Old city');

    expect($spring->fresh()->country_id)->toBeNull()
        ->and($spring->fresh()->nearest_city_id)->toBeNull();
});

test('source details display the Russian country name and fallback when no translation exists', function () {
    app()->setLocale('ru');
    $spring = createGeocodingUiSpring(catalogLinks('RU', 'Moscow'));

    Livewire::test(Show::class, ['springId' => $spring->id])
        ->assertSee('Страна')
        ->assertSee('Россия')
        ->assertSee('Ближайший населённый пункт')
        ->assertDontSee('Russia');

    $spring->forceFill(['country_id' => catalogCountry('FR', 'France')->id])->saveQuietly();

    Livewire::test(Show::class, ['springId' => $spring->id])->assertSee('France');
});

test('spring JSON resources expose the derived country and nearest city', function () {
    $spring = createGeocodingUiSpring(catalogLinks('RU', 'Moscow', 'Moscow Oblast'));

    $this->getJson('/api/v1/springs/'.$spring->id)
        ->assertOk()
        ->assertJsonPath('data.country', 'Russia')
        ->assertJsonPath('data.country_ru', 'Россия')
        ->assertJsonPath('data.country_code', 'RU')
        ->assertJsonPath('data.city', 'Moscow')
        ->assertJsonPath('data.region', 'Moscow Oblast');

    $legacy = (new SpringResource($spring))->resolve();

    expect($legacy)->toMatchArray([
        'country' => 'Russia',
        'country_ru' => 'Россия',
        'country_code' => 'RU',
        'city' => 'Moscow',
        'region' => 'Moscow Oblast',
    ]);
});
