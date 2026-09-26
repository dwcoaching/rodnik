<?php

declare(strict_types=1);

use App\Actions\Springs\PatchSpringsLocationAction;
use App\Models\Report;
use App\Models\Spring;
use App\Models\SpringRevision;
use App\Models\SpringTile;
use App\Models\User;
use App\Models\WateredSpringTile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake(SpringTile::DISK);
    Storage::fake(WateredSpringTile::DISK);
    $this->actingAs(User::factory()->create());
    Queue::fake();
});

test('moving a source across tile boundaries removes cached old and new locations', function (string $tileClass, int $zoom) {
    $spring = Spring::factory()->create(['latitude' => 10.111111, 'longitude' => 20.222222]);
    Report::factory()->create(['spring_id' => $spring->id, 'from_osm' => null, 'hidden_at' => null]);
    $oldTiles = $tileClass::fromCoordinates($spring->longitude, $spring->latitude);
    $newTiles = $tileClass::fromCoordinates(50.666666, 40.555555);
    $oldDetail = $oldTiles->firstWhere('z', $zoom);
    $newDetail = $newTiles->firstWhere('z', $zoom);

    expect($oldDetail->path())->not->toBe($newDetail->path());

    foreach ($oldTiles->merge($newTiles)->unique('id') as $tile) {
        $tile->saveFile();
        Storage::disk($tileClass::DISK)->assertExists($tile->path());
    }

    app(PatchSpringsLocationAction::class)($spring, ['latitude' => 40.555555, 'longitude' => 50.666666]);

    foreach ($oldTiles->merge($newTiles)->unique('id') as $tile) {
        Storage::disk($tileClass::DISK)->assertMissing($tile->path());
        expect($tile->fresh()->generated_at)->toBeNull();
    }

    expect(json_decode($oldDetail->fresh()->geoJSON(), true)['features'])->toBeEmpty()
        ->and(array_column(json_decode($newDetail->fresh()->geoJSON(), true)['features'], 'id'))->toContain($spring->id);
})->with([
    'detailed source tiles' => [SpringTile::class, 8],
    'report summary tiles' => [WateredSpringTile::class, 5],
]);

test('saving unchanged coordinates keeps generated tile caches and avoids a revision', function () {
    $spring = Spring::factory()->create(['latitude' => 55.75, 'longitude' => 37.62]);
    $tiles = SpringTile::fromCoordinates($spring->longitude, $spring->latitude);

    foreach ($tiles as $tile) {
        $tile->saveFile();
    }

    app(PatchSpringsLocationAction::class)($spring->fresh(), ['latitude' => '55.750000', 'longitude' => 37.62]);

    foreach ($tiles as $tile) {
        Storage::disk(SpringTile::DISK)->assertExists($tile->path());
        expect($tile->fresh()->generated_at)->not->toBeNull();
    }

    expect(SpringRevision::query()->where('spring_id', $spring->id)->count())->toBe(0);
});
