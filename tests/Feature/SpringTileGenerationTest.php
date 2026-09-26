<?php

declare(strict_types=1);

use App\Enums\ReportQuality;
use App\Enums\ReportState;
use App\Models\Report;
use App\Models\Spring;
use App\Models\SpringTile;
use App\Models\WateredSpringTile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('spring tile generation writes geojson', function () {
    Spring::factory()->create([
        'latitude' => 10.111111,
        'longitude' => 20.222222,
    ]);

    Storage::fake(SpringTile::DISK);

    $tile = SpringTile::fromXYZ(0, 0, 0);

    $tile->saveFile();

    Storage::disk(SpringTile::DISK)->assertExists($tile->path());
    expect(json_decode(Storage::disk(SpringTile::DISK)->get($tile->path()), true))
        ->toHaveKey('type', 'FeatureCollection')
        ->toHaveKey('features.0.geometry.type', 'Point');
});

test('spring and watered tiles use visible reports for score and not found', function () {
    $spring = Spring::factory()->create([
        'latitude' => 10.111111,
        'longitude' => 20.222222,
    ]);

    Report::factory()->for($spring)->create([
        'state' => ReportState::NotFound,
        'quality' => null,
    ]);
    Report::factory()->for($spring)->create([
        'state' => ReportState::Running,
        'quality' => null,
    ]);
    Report::factory()->for($spring)->create([
        'state' => null,
        'quality' => ReportQuality::Good,
        'hidden_at' => now(),
    ]);
    Report::factory()->for($spring)->create([
        'state' => null,
        'quality' => ReportQuality::Good,
        'from_osm' => true,
    ]);

    $tiles = [
        SpringTile::fromXYZ(0, 0, 0),
        SpringTile::fromCoordinates($spring->longitude, $spring->latitude)->firstWhere('z', 5),
        SpringTile::fromCoordinates($spring->longitude, $spring->latitude)->firstWhere('z', 8),
        WateredSpringTile::fromXYZ(0, 0, 0),
        WateredSpringTile::fromCoordinates($spring->longitude, $spring->latitude)->firstWhere('z', 5),
    ];

    expect($tiles[3])->toBeInstanceOf(WateredSpringTile::class);

    foreach ($tiles as $tile) {
        $feature = collect(json_decode($tile->geoJSON(), true)['features'])
            ->firstWhere('id', $spring->id);

        expect($feature['properties'])
            ->toMatchArray([
                'hasReports' => 2,
                'score' => 0.0,
                'notFound' => false,
            ])
            ->not->toHaveKey('waterConfirmed')
            ->and($feature['properties']['notFound'])->toBeBool();
    }
});

test('tiles with reports include every report quality and exclude sources without visible reports', function () {
    $coordinates = ['latitude' => 10.111111, 'longitude' => 20.222222];
    $visible = collect([ReportQuality::Good, ReportQuality::Uncertain, ReportQuality::Bad, null])
        ->map(function (?ReportQuality $quality) use ($coordinates): Spring {
            $spring = Spring::factory()->create($coordinates);
            Report::factory()->for($spring)->create(['quality' => $quality, 'state' => null]);

            return $spring;
        });
    Spring::factory()->create($coordinates);
    Report::factory()->for(Spring::factory()->state($coordinates))->create(['hidden_at' => now()]);
    Report::factory()->for(Spring::factory()->state($coordinates))->create(['from_osm' => true]);

    foreach ([
        WateredSpringTile::fromXYZ(0, 0, 0),
        WateredSpringTile::fromCoordinates($coordinates['longitude'], $coordinates['latitude'])->firstWhere('z', 5),
    ] as $tile) {
        $features = collect(json_decode($tile->geoJSON(), true)['features']);

        expect($features->pluck('id')->all())->toEqualCanonicalizing($visible->pluck('id')->all())
            ->and($features->pluck('properties.hasReports')->all())->toBe([1, 1, 1, 1]);
    }
});

test('not found springs remain in tile payloads at every zoom level', function () {
    $spring = Spring::factory()->create([
        'latitude' => 10.111111,
        'longitude' => 20.222222,
    ]);

    Report::factory()->for($spring)->create([
        'state' => ReportState::NotFound,
        'quality' => null,
    ]);

    $coarseTiles = [
        SpringTile::fromXYZ(0, 0, 0),
        SpringTile::fromCoordinates($spring->longitude, $spring->latitude)->firstWhere('z', 5),
        WateredSpringTile::fromXYZ(0, 0, 0),
        WateredSpringTile::fromCoordinates($spring->longitude, $spring->latitude)->firstWhere('z', 5),
    ];

    foreach ($coarseTiles as $tile) {
        $coarseFeature = collect(json_decode($tile->geoJSON(), true)['features'])
            ->firstWhere('id', $spring->id);

        expect($coarseFeature)->not->toBeNull()
            ->and($coarseFeature['properties']['notFound'])->toBeTrue();
    }

    $detailTile = SpringTile::fromCoordinates($spring->longitude, $spring->latitude)
        ->firstWhere('z', 8);
    $detailFeature = collect(json_decode($detailTile->geoJSON(), true)['features'])
        ->firstWhere('id', $spring->id);

    expect($detailFeature)->not->toBeNull()
        ->and($detailFeature['properties']['notFound'])->toBeTrue();
});

test('not found report visibility does not remove springs from coarse tiles', function () {
    $hiddenNotFound = Spring::factory()->create([
        'latitude' => 10.111111,
        'longitude' => 20.222222,
    ]);
    Report::factory()->for($hiddenNotFound)->create([
        'state' => ReportState::NotFound,
        'quality' => null,
        'hidden_at' => now(),
    ]);

    $osmNotFound = Spring::factory()->create([
        'latitude' => 10.111111,
        'longitude' => 20.222222,
    ]);
    Report::factory()->for($osmNotFound)->create([
        'state' => ReportState::NotFound,
        'quality' => null,
        'from_osm' => true,
    ]);

    $visibleNotFound = Spring::factory()->create([
        'latitude' => 10.111111,
        'longitude' => 20.222222,
    ]);
    Report::factory()->for($visibleNotFound)->create([
        'state' => ReportState::NotFound,
        'quality' => null,
    ]);

    foreach ([
        SpringTile::fromXYZ(0, 0, 0),
        SpringTile::fromCoordinates($hiddenNotFound->longitude, $hiddenNotFound->latitude)->firstWhere('z', 5),
    ] as $tile) {
        $featureIds = collect(json_decode($tile->geoJSON(), true)['features'])->pluck('id');

        expect($featureIds)
            ->toContain($hiddenNotFound->id)
            ->toContain($osmNotFound->id)
            ->toContain($visibleNotFound->id);
    }
});
