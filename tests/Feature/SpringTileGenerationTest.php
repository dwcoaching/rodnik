<?php

declare(strict_types=1);

use App\Models\Spring;
use App\Models\SpringTile;
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
