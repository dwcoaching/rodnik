<?php

declare(strict_types=1);

use App\Models\Map;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('the removed filter is cleared from saved maps without changing the rest of the snapshot', function (bool $confirmed) {
    Storage::fake('tiles');
    Storage::fake('watered-tiles');
    $state = Map::factory()->make()->state;
    unset($state['filters']['with_reports']);
    $state['filters']['confirmed'] = $confirmed;
    $map = Map::factory()->create(['state' => $state, 'version' => 7, 'views_count' => 12]);
    $originalAttributes = $map->getAttributes();
    $migration = require database_path('migrations/2026_09_08_193449_remove_confirmed_water_filter_data.php');

    $migration->up();
    $migration->up();

    unset($state['filters']['confirmed']);
    $state['filters']['with_reports'] = false;

    expect($map->refresh()->state)->toEqual($state)
        ->and($map->getAttributes())->toEqual([
            ...$originalAttributes,
            'state' => $map->getRawOriginal('state'),
        ]);
})->with([true, false]);

test('cleanup preserves an explicitly chosen reports filter and already updated snapshots', function () {
    Storage::fake('tiles');
    Storage::fake('watered-tiles');
    $state = Map::factory()->make()->state;
    $state['filters']['with_reports'] = true;
    $current = Map::factory()->create(['state' => $state]);
    $legacy = Map::factory()->create(['state' => array_replace_recursive($state, ['filters' => ['confirmed' => true]])]);
    $migration = require database_path('migrations/2026_09_08_193449_remove_confirmed_water_filter_data.php');

    $migration->up();

    expect($current->refresh()->state)->toEqual($state)
        ->and($legacy->refresh()->state)->toEqual($state);
});

test('cleanup removes stale static geojson and invalidates both tile tables', function () {
    foreach (['tiles' => 'spring_tiles', 'watered-tiles' => 'watered_spring_tiles'] as $diskName => $table) {
        Storage::fake($diskName);
        Storage::disk($diskName)->put('0/0/0.json', '{"waterConfirmed":true}');
        Storage::disk($diskName)->put('5/1/1.json', '{"waterConfirmed":false}');
        Storage::disk($diskName)->put('.gitignore', '*');
        DB::table($table)->insert([
            'z' => 0,
            'x' => 0,
            'y' => 0,
            'generated_at' => now(),
        ]);
    }

    $migration = require database_path('migrations/2026_09_08_193449_remove_confirmed_water_filter_data.php');
    $migration->up();

    foreach (['tiles' => 'spring_tiles', 'watered-tiles' => 'watered_spring_tiles'] as $diskName => $table) {
        Storage::disk($diskName)->assertMissing(['0/0/0.json', '5/1/1.json']);
        Storage::disk($diskName)->assertExists('.gitignore');
        expect(DB::table($table)->whereNotNull('generated_at')->exists())->toBeFalse();
        expect(DB::table($table)->where(['z' => 0, 'x' => 0, 'y' => 0])->exists())->toBeTrue();
    }
});
