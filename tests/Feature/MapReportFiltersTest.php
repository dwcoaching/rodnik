<?php

declare(strict_types=1);

use App\Enums\ReportQuality;
use App\Livewire\Duo\Reports\Index;
use App\Models\Report;
use App\Models\Spring;
use App\Models\TrackPolygon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->bounds = ['west' => 10, 'south' => 40, 'east' => 20, 'north' => 50];
    $this->mapFilters = [
        'spring' => true,
        'water_well' => true,
        'water_tap' => true,
        'drinking_water' => true,
        'fountain' => true,
        'other' => true,
        'confirmed' => false,
        'along' => false,
    ];
    $this->polygonAttributes = [
        'polygon' => [
            'type' => 'Polygon',
            'coordinates' => [[[10, 40], [20, 40], [20, 43], [13, 43], [13, 50], [10, 50], [10, 40]]],
        ],
        'latitude_from' => 40,
        'latitude_to' => 50,
        'longitude_from' => 10,
        'longitude_to' => 20,
    ];
});

test('each source type filter excludes exactly its matching source type', function (string $filter, string $type) {
    $reports = collect(Spring::TYPES)->mapWithKeys(function (string $sourceType) {
        return [$sourceType => Report::factory()->for(Spring::factory()->state([
            'type' => $sourceType, 'longitude' => 15, 'latitude' => 45,
        ]))->create()];
    });
    $expectedIds = $reports->except($type)->pluck('id')->reverse()->values()->all();

    Livewire::test(Index::class)
        ->call('updateMap', $this->bounds, [...$this->mapFilters, $filter => false])
        ->assertHasNoErrors()
        ->assertViewHas('lastReports', fn ($visible) => $visible->modelKeys() === $expectedIds)
        ->assertViewHas('hasMore', false);
})->with([
    'springs' => ['spring', 'Spring'],
    'water wells' => ['water_well', 'Water well'],
    'water taps' => ['water_tap', 'Water tap'],
    'drinking water' => ['drinking_water', 'Drinking water source'],
    'fountains' => ['fountain', 'Fountain'],
    'other sources' => ['other', 'Water source'],
]);

test('disabled source types preserve the map behavior for unknown and missing types', function () {
    $reports = collect([...Spring::TYPES, 'Unclassified source', null])->map(function (?string $type) {
        return Report::factory()->for(Spring::factory()->state([
            'type' => $type, 'longitude' => 15, 'latitude' => 45,
        ]))->create();
    });
    $filters = array_fill_keys(array_keys($this->mapFilters), false);
    $expectedIds = $reports->take(-2)->pluck('id')->reverse()->values()->all();

    Livewire::test(Index::class)
        ->call('updateMap', $this->bounds, $filters)
        ->assertHasNoErrors()
        ->assertViewHas('lastReports', fn ($visible) => $visible->modelKeys() === $expectedIds);
});

test('confirmed water filters sources using their visible quality votes and retains their older negative reports', function () {
    $confirmed = Spring::factory()->create(['longitude' => 15, 'latitude' => 45]);
    $expected = Report::factory()->for($confirmed)->sequence(
        ['quality' => ReportQuality::Good],
        ['quality' => ReportQuality::Good],
        ['quality' => ReportQuality::Bad],
        ['quality' => null],
    )->count(4)->create();
    Report::factory()->count(3)->for($confirmed)->create(['quality' => ReportQuality::Bad, 'hidden_at' => now()]);
    Report::factory()->count(3)->for($confirmed)->create(['quality' => ReportQuality::Bad, 'from_osm' => true]);

    $tied = Spring::factory()->create(['longitude' => 15, 'latitude' => 45]);
    Report::factory()->for($tied)->sequence(
        ['quality' => ReportQuality::Good],
        ['quality' => ReportQuality::Uncertain],
    )->count(2)->create();
    Report::factory()->count(3)->for($tied)->create(['quality' => ReportQuality::Good, 'hidden_at' => now()]);
    Report::factory()->count(3)->for($tied)->create(['quality' => ReportQuality::Good, 'from_osm' => true]);
    Report::factory()->for(Spring::factory()->state([
        'longitude' => 15, 'latitude' => 45,
    ]))->create(['quality' => null]);

    expect($confirmed->waterConfirmed())->toBeTrue()
        ->and($tied->waterConfirmed())->toBeFalse();

    Livewire::test(Index::class)
        ->call('updateMap', $this->bounds, [...$this->mapFilters, 'confirmed' => true])
        ->assertHasNoErrors()
        ->assertViewHas('lastReports', fn ($visible) => $visible->modelKeys() === $expected->pluck('id')->reverse()->values()->all())
        ->assertViewHas('hasMore', false);
});

test('along tracks checks the saved geometry including holes and boundaries', function (array $geometry, array $inside, array $outside) {
    $polygon = TrackPolygon::factory()->create([...$this->polygonAttributes, 'polygon' => $geometry]);
    $matching = collect($inside)->map(fn (array $point) => Report::factory()->for(Spring::factory()->state([
        'longitude' => $point[0], 'latitude' => $point[1],
    ]))->create());
    collect($outside)->each(fn (array $point) => Report::factory()->for(Spring::factory()->state([
        'longitude' => $point[0], 'latitude' => $point[1],
    ]))->create());

    Livewire::test(Index::class)
        ->call('updateMap', $this->bounds, [...$this->mapFilters, 'along' => true], $polygon->hash)
        ->assertHasNoErrors()
        ->assertSet('trackPolygonHash', $polygon->hash)
        ->assertViewHas('lastReports', fn ($visible) => $visible->modelKeys() === $matching->pluck('id')->reverse()->values()->all())
        ->assertViewHas('hasMore', false);
})->with([
    'concave corridor excludes points inside only its bounding rectangle' => [
        ['type' => 'Polygon', 'coordinates' => [[[10, 40], [20, 40], [20, 43], [13, 43], [13, 50], [10, 50], [10, 40]]]],
        [[12, 48], [18, 42], [13, 48], [10, 40]],
        [[18, 48], [9, 42], [12, 51]],
    ],
    'hole interior excluded while exterior and hole boundaries included' => [
        ['type' => 'Polygon', 'coordinates' => [
            [[10, 40], [20, 40], [20, 50], [10, 50], [10, 40]],
            [[14, 44], [14, 46], [16, 46], [16, 44], [14, 44]],
        ]],
        [[12, 48], [14, 45], [20, 45]],
        [[15, 45], [9, 42]],
    ],
    'all multipolygon components included' => [
        ['type' => 'MultiPolygon', 'coordinates' => [
            [[[10, 40], [13, 40], [13, 43], [10, 43], [10, 40]]],
            [[[17, 47], [20, 47], [20, 50], [17, 50], [17, 47]]],
        ]],
        [[12, 42], [18, 48], [17, 48]],
        [[15, 45], [18, 42]],
    ],
]);

test('source type confirmed water and track filters combine before pagination', function () {
    $polygon = TrackPolygon::factory()->create($this->polygonAttributes);
    $user = User::factory()->create();
    $matching = Report::factory()->count(25)->for($user)->for(Spring::factory()->state([
        'type' => 'Spring', 'longitude' => 12, 'latitude' => 48,
    ]))->create(['quality' => ReportQuality::Good, 'created_at' => now()->subDay()]);
    Report::factory()->count(25)->for($user)->for(Spring::factory()->state([
        'type' => 'Spring', 'longitude' => 18, 'latitude' => 48,
    ]))->create(['quality' => ReportQuality::Good, 'created_at' => now()]);
    Report::factory()->for($user)->for(Spring::factory()->state([
        'type' => 'Water well', 'longitude' => 12, 'latitude' => 48,
    ]))->create(['quality' => ReportQuality::Good, 'created_at' => now()]);
    Report::factory()->for($user)->for(Spring::factory()->state([
        'type' => 'Spring', 'longitude' => 12, 'latitude' => 48,
    ]))->create(['quality' => ReportQuality::Bad, 'created_at' => now()]);
    $filters = [...$this->mapFilters, 'water_well' => false, 'confirmed' => true, 'along' => true];
    $expectedIds = $matching->pluck('id')->reverse()->values()->all();

    Livewire::test(Index::class)
        ->call('updateMap', $this->bounds, $filters, $polygon->hash)
        ->assertHasNoErrors()
        ->assertSet('limit', 24)
        ->assertViewHas('lastReports', fn ($visible) => $visible->modelKeys() === array_slice($expectedIds, 0, 24))
        ->assertViewHas('hasMore', true)
        ->call('showMore')
        ->assertSet('limit', 48)
        ->assertViewHas('lastReports', fn ($visible) => $visible->modelKeys() === $expectedIds)
        ->assertViewHas('hasMore', false)
        ->call('updateMap', $this->bounds, $filters, $polygon->hash)
        ->assertSet('limit', 48)
        ->call('updateMap', $this->bounds, [...$filters, 'water_well' => true], $polygon->hash)
        ->assertSet('limit', 24)
        ->assertViewHas('hasMore', true);
});

test('changing the track resets pagination with unchanged map bounds and filters', function () {
    $firstPolygon = TrackPolygon::factory()->create($this->polygonAttributes);
    $secondPolygon = TrackPolygon::factory()->create([
        ...$this->polygonAttributes,
        'polygon' => ['type' => 'Polygon', 'coordinates' => [[[17, 47], [20, 47], [20, 50], [17, 50], [17, 47]]]],
        'latitude_from' => 47,
        'longitude_from' => 17,
    ]);
    $firstReport = Report::factory()->for(Spring::factory()->state(['longitude' => 12, 'latitude' => 48]))->create();
    $secondReport = Report::factory()->for(Spring::factory()->state(['longitude' => 18, 'latitude' => 48]))->create();
    $filters = [...$this->mapFilters, 'along' => true];

    Livewire::test(Index::class)
        ->call('updateMap', $this->bounds, $filters, $firstPolygon->hash)
        ->assertViewHas('lastReports', fn ($visible) => $visible->modelKeys() === [$firstReport->id])
        ->call('showMore')
        ->assertSet('limit', 48)
        ->call('updateMap', $this->bounds, $filters, $secondPolygon->hash)
        ->assertSet('limit', 24)
        ->assertSet('trackPolygonHash', $secondPolygon->hash)
        ->assertViewHas('lastReports', fn ($visible) => $visible->modelKeys() === [$secondReport->id]);
});

test('along tracks waits for a polygon instead of displaying unrestricted reports', function () {
    $report = Report::factory()->for(Spring::factory()->state(['longitude' => 12, 'latitude' => 48]))->create();
    $polygon = TrackPolygon::factory()->create($this->polygonAttributes);
    $filters = [...$this->mapFilters, 'along' => true];

    Livewire::test(Index::class)
        ->call('updateMap', $this->bounds, $filters)
        ->assertHasNoErrors()
        ->assertViewHas('lastReports', fn ($visible) => $visible->isEmpty())
        ->assertViewHas('hasMore', false)
        ->call('updateMap', $this->bounds, $filters, $polygon->hash)
        ->assertViewHas('lastReports', fn ($visible) => $visible->modelKeys() === [$report->id])
        ->call('updateMap', $this->bounds, $filters)
        ->assertSet('trackPolygonHash', null)
        ->assertViewHas('lastReports', fn ($visible) => $visible->isEmpty());
});

test('disabled along tracks clears and ignores polygon hashes', function () {
    $report = Report::factory()->for(Spring::factory()->state(['longitude' => 18, 'latitude' => 48]))->create();
    $polygon = TrackPolygon::factory()->create($this->polygonAttributes);

    Livewire::test(Index::class)
        ->call('updateMap', $this->bounds, [...$this->mapFilters, 'along' => true], $polygon->hash)
        ->assertViewHas('lastReports', fn ($visible) => $visible->isEmpty())
        ->call('updateMap', $this->bounds, $this->mapFilters, str_repeat('a', 64))
        ->assertHasNoErrors()
        ->assertSet('trackPolygonHash', null)
        ->assertViewHas('lastReports', fn ($visible) => $visible->modelKeys() === [$report->id]);
});

test('invalid map updates preserve bounds filters track and pagination atomically', function (string $invalidPart) {
    $polygon = TrackPolygon::factory()->create($this->polygonAttributes);
    $filters = [...$this->mapFilters, 'along' => true];
    $newBounds = ['west' => 11, 'south' => 41, 'east' => 19, 'north' => 49];
    $newFilters = [...$filters, 'confirmed' => true];
    $newHash = $polygon->hash;

    match ($invalidPart) {
        'bounds' => $newBounds['north'] = 91,
        'missing filter' => $newFilters = ['along' => true],
        'unknown filter' => $newFilters['unexpected'] = true,
        'invalid filter value' => $newFilters['spring'] = 'invalid',
        'malformed hash' => $newHash = 'invalid',
        'unknown hash' => $newHash = str_repeat('a', 64),
    };

    Livewire::test(Index::class)
        ->call('updateMap', $this->bounds, $filters, $polygon->hash)
        ->call('showMore')
        ->call('updateMap', $newBounds, $newFilters, $newHash)
        ->assertHasErrors()
        ->assertSet('bounds', $this->bounds)
        ->assertSet('filters', $filters)
        ->assertSet('trackPolygonHash', $polygon->hash)
        ->assertSet('limit', 48);
})->with(['bounds', 'missing filter', 'unknown filter', 'invalid filter value', 'malformed hash', 'unknown hash']);

test('invalid stored geometry cannot enable an unrestricted track report feed', function () {
    $polygon = TrackPolygon::factory()->create([
        ...$this->polygonAttributes,
        'polygon' => ['type' => 'Polygon', 'coordinates' => [[[10, 40], [20, 50], [20, 40], [10, 50], [10, 40]]]],
    ]);
    Report::factory()->for(Spring::factory()->state(['longitude' => 12, 'latitude' => 48]))->create();

    Livewire::test(Index::class)
        ->call('updateMap', $this->bounds, [...$this->mapFilters, 'along' => true], $polygon->hash)
        ->assertHasErrors()
        ->assertSet('bounds', null)
        ->assertSet('trackPolygonHash', null)
        ->assertViewHas('lastReports', fn ($visible) => $visible->isEmpty());
});

test('user report feeds remain independent of map type confirmed and track filters', function () {
    $user = User::factory()->create();
    $report = Report::factory()->for($user)->for(Spring::factory()->state([
        'type' => 'Spring', 'longitude' => 100, 'latitude' => 0,
    ]))->create(['quality' => ReportQuality::Bad]);
    $filters = [...array_fill_keys(array_keys($this->mapFilters), false), 'confirmed' => true, 'along' => true];

    Livewire::test(Index::class, ['userId' => $user->id])
        ->call('updateMap', $this->bounds, $filters)
        ->assertHasNoErrors()
        ->call('showMore')
        ->assertSet('limit', 12)
        ->assertViewHas('lastReports', fn ($visible) => $visible->modelKeys() === [$report->id]);
});

test('track pagination scans past a full batch of rejected reports with stable ordering', function (string $matchingDate, string $rejectedDate) {
    $polygon = TrackPolygon::factory()->create($this->polygonAttributes);
    $user = User::factory()->create();
    $inside = Spring::factory()->create(['type' => 'Spring', 'longitude' => 12, 'latitude' => 48]);
    $outside = Spring::factory()->create(['type' => 'Spring', 'longitude' => 18, 'latitude' => 48]);
    $matching = Report::factory()->count(25)->for($user)->for($inside)->create(['created_at' => $matchingDate]);
    Report::factory()->count(260)->for($user)->for($outside)->create(['created_at' => $rejectedDate]);
    $expectedIds = $matching->pluck('id')->reverse()->values()->all();

    Livewire::test(Index::class)
        ->call('updateMap', $this->bounds, [...$this->mapFilters, 'along' => true], $polygon->hash)
        ->assertHasNoErrors()
        ->assertViewHas('lastReports', fn ($visible) => $visible->modelKeys() === array_slice($expectedIds, 0, 24))
        ->assertViewHas('hasMore', true)
        ->call('showMore')
        ->assertViewHas('lastReports', fn ($visible) => $visible->modelKeys() === $expectedIds)
        ->assertViewHas('hasMore', false);
})->with([
    'matching reports share the rejected timestamp' => ['2026-01-01 12:00:00', '2026-01-01 12:00:00'],
    'matching reports have an older timestamp' => ['2026-01-01 12:00:00', '2026-01-02 12:00:00'],
]);

test('track candidate batches continue from dated reports into and through null timestamps', function () {
    $polygon = TrackPolygon::factory()->create($this->polygonAttributes);
    $user = User::factory()->create();
    $inside = Spring::factory()->create(['type' => 'Spring', 'longitude' => 12, 'latitude' => 48]);
    $outside = Spring::factory()->create(['type' => 'Spring', 'longitude' => 18, 'latitude' => 48]);
    $matching = Report::withoutTimestamps(fn () => Report::factory()->count(25)->for($user)->for($inside)->create(['created_at' => null]));
    Report::withoutTimestamps(fn () => Report::factory()->count(260)->for($user)->for($outside)->create(['created_at' => null]));
    Report::factory()->count(250)->for($user)->for($outside)->create(['created_at' => '2026-01-01 12:00:00']);
    $expectedIds = $matching->pluck('id')->reverse()->values()->all();
    $component = app(Index::class);

    $component->updateMap($this->bounds, [...$this->mapFilters, 'along' => true], $polygon->hash);
    $firstPage = $component->render()->getData();

    expect($matching->every(fn (Report $report): bool => $report->created_at === null))->toBeTrue()
        ->and($firstPage['lastReports']->modelKeys())->toBe(array_slice($expectedIds, 0, 24))
        ->and($firstPage['hasMore'])->toBeTrue();

    $component->showMore();
    $secondPage = $component->render()->getData();

    expect($secondPage['lastReports']->modelKeys())->toBe($expectedIds)
        ->and($secondPage['hasMore'])->toBeFalse();
});
