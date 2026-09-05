<?php

declare(strict_types=1);

use App\Livewire\Duo\Reports\Index;
use App\Models\Report;
use App\Models\Spring;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->bounds = ['west' => 10, 'south' => 40, 'east' => 20, 'north' => 50];
});

test('home reports wait for map bounds without flashing worldwide reports', function () {
    Report::factory()->create();

    Livewire::test(Index::class)
        ->assertViewHas('lastReports', fn ($reports) => $reports->isEmpty())
        ->assertViewHas('hasMore', false)
        ->call('showMore')
        ->assertSet('limit', 12)
        ->assertViewHas('lastReports', fn ($reports) => $reports->isEmpty());
});

test('reports follow map bounds including edges and current spring coordinates', function () {
    $inside = collect([[10, 40], [20, 50], [15, 45]])->map(function (array $coordinates) {
        return Report::factory()->for(Spring::factory()->state([
            'longitude' => $coordinates[0], 'latitude' => $coordinates[1],
        ]))->create(['old_longitude' => 100, 'old_latitude' => 0]);
    });

    $outside = collect([[9.999, 45], [20.001, 45], [15, 39.999], [15, 50.001]])->map(function (array $coordinates) {
        return Report::factory()->for(Spring::factory()->state([
            'longitude' => $coordinates[0], 'latitude' => $coordinates[1],
        ]))->create(['old_longitude' => 15, 'old_latitude' => 45]);
    });

    Livewire::test(Index::class)
        ->call('updateBounds', $this->bounds)
        ->assertViewHas('lastReports', fn ($reports) => $reports->modelKeys() === $inside->pluck('id')->reverse()->values()->all())
        ->call('updateBounds', ['west' => 9, 'south' => 44, 'east' => 10, 'north' => 46])
        ->assertViewHas('lastReports', fn ($reports) => $reports->modelKeys() === [$outside->first()->id]);
});

test('map reports exclude hidden reports hidden springs OSM imports and merged springs', function () {
    $spring = Spring::factory()->create(['longitude' => 15, 'latitude' => 45]);
    $visible = Report::factory()->for($spring)->create();
    Report::factory()->for($spring)->create(['hidden_at' => now()]);
    Report::factory()->for($spring)->create(['from_osm' => true]);
    Report::factory()->for(Spring::factory()->state([
        'longitude' => 15, 'latitude' => 45, 'hidden_at' => now(),
    ]))->create();
    Report::factory()->for(Spring::factory()->state([
        'longitude' => 15, 'latitude' => 45, 'redirect_to_spring_id' => $spring->id,
    ]))->create();

    Livewire::test(Index::class)
        ->call('updateBounds', $this->bounds)
        ->assertViewHas('lastReports', fn ($reports) => $reports->modelKeys() === [$visible->id])
        ->assertViewHas('hasMore', false);
});

test('map reports support the date line and the whole world', function (array $bounds, array $visibleLongitudes) {
    $reports = collect([-180, -179, 0, 179, 180])->mapWithKeys(function (int $longitude) {
        return [$longitude => Report::factory()->for(Spring::factory()->state([
            'longitude' => $longitude, 'latitude' => 0,
        ]))->create()];
    });
    Report::factory()->for(Spring::factory()->state([
        'longitude' => 179, 'latitude' => 60,
    ]))->create();

    Livewire::test(Index::class)
        ->call('updateBounds', $bounds)
        ->assertViewHas('lastReports', fn ($visible) => $visible->modelKeys() === $reports->only($visibleLongitudes)->pluck('id')->reverse()->values()->all());
})->with([
    'across the date line' => [
        ['west' => 170, 'south' => -10, 'east' => -170, 'north' => 10],
        [-180, -179, 179, 180],
    ],
    'all longitudes' => [
        ['west' => -180, 'south' => -10, 'east' => 180, 'north' => 10],
        [-180, -179, 0, 179, 180],
    ],
]);

test('show more stays in the map area with stable newest first ordering and resets after a move', function () {
    $spring = Spring::factory()->create(['longitude' => 15, 'latitude' => 45]);
    $recent = Report::factory()->count(48)->for($spring)->create(['created_at' => now()]);
    $oldest = Report::factory()->for($spring)->create(['created_at' => now()->subDay()]);
    Report::factory()->count(25)->for(Spring::factory()->state([
        'longitude' => 100, 'latitude' => 0,
    ]))->create(['created_at' => now()->addDay()]);
    $orderedIds = $recent->modelKeys();
    rsort($orderedIds);

    Livewire::test(Index::class)
        ->call('updateBounds', $this->bounds)
        ->assertSet('limit', 24)
        ->assertViewHas('lastReports', fn ($reports) => $reports->modelKeys() === array_slice($orderedIds, 0, 24))
        ->assertViewHas('hasMore', true)
        ->call('showMore')
        ->assertSet('limit', 48)
        ->assertViewHas('lastReports', fn ($reports) => $reports->modelKeys() === $orderedIds)
        ->assertViewHas('hasMore', true)
        ->call('updateBounds', $this->bounds)
        ->assertSet('limit', 48)
        ->call('showMore')
        ->assertViewHas('lastReports', fn ($reports) => $reports->modelKeys() === [...$orderedIds, $oldest->id])
        ->assertViewHas('hasMore', false)
        ->call('updateBounds', ['west' => 30, 'south' => 40, 'east' => 40, 'north' => 50])
        ->assertSet('limit', 24)
        ->assertViewHas('lastReports', fn ($reports) => $reports->isEmpty())
        ->assertViewHas('hasMore', false);
});

test('exactly twenty four reports do not offer an empty next page', function () {
    Report::factory()->count(24)->for(Spring::factory()->state([
        'longitude' => 15, 'latitude' => 45,
    ]))->create();

    Livewire::test(Index::class)
        ->call('updateBounds', $this->bounds)
        ->assertViewHas('lastReports', fn ($reports) => $reports->count() === 24)
        ->assertViewHas('hasMore', false)
        ->assertDontSee(__('ui.home.show_more_area_reports'));
});

test('invalid map bounds preserve the previous area and pagination', function (array $invalidBounds) {
    Livewire::test(Index::class)
        ->call('updateBounds', $this->bounds)
        ->call('showMore')
        ->call('updateBounds', $invalidBounds)
        ->assertHasErrors()
        ->assertSet('bounds', $this->bounds)
        ->assertSet('limit', 48);
})->with([
    'missing coordinates' => [[]],
    'non numeric longitude' => [['west' => 'invalid', 'south' => 40, 'east' => 20, 'north' => 50]],
    'west out of range' => [['west' => -181, 'south' => 40, 'east' => 20, 'north' => 50]],
    'east out of range' => [['west' => 10, 'south' => 40, 'east' => 181, 'north' => 50]],
    'south out of range' => [['west' => 10, 'south' => -91, 'east' => 20, 'north' => 50]],
    'north out of range' => [['west' => 10, 'south' => 40, 'east' => 20, 'north' => 91]],
    'inverted latitude' => [['west' => 10, 'south' => 50, 'east' => 20, 'north' => 40]],
]);

test('a user report feed remains independent of map bounds', function () {
    $user = User::factory()->create();
    $reports = Report::factory()->count(13)->for($user)->for(Spring::factory()->state([
        'longitude' => 100, 'latitude' => 0,
    ]))->sequence(fn (Sequence $sequence) => [
        'created_at' => now()->subMinutes(13 - $sequence->index),
        'from_osm' => $sequence->index === 12 ? true : null,
    ])->create();
    $expectedIds = $reports->pluck('id')->reverse()->take(12)->values()->all();

    Livewire::test(Index::class, ['userId' => $user->id])
        ->assertViewHas('lastReports', fn ($visible) => $visible->modelKeys() === $expectedIds)
        ->call('updateBounds', $this->bounds)
        ->call('showMore')
        ->assertSet('limit', 12)
        ->assertViewHas('lastReports', fn ($visible) => $visible->modelKeys() === $expectedIds);
});
