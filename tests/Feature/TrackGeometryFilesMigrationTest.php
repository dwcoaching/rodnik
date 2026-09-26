<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->originalConnection = DB::getDefaultConnection();
    $this->originalSchema = Schema::getFacadeRoot();
    config(['database.connections.track_files_migration' => [
        'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
    ]]);
    DB::purge('track_files_migration');
    DB::setDefaultConnection('track_files_migration');
    Schema::swap(DB::connection()->getSchemaBuilder());

    Schema::create('tracks', function (Blueprint $table): void {
        $table->id();
        $table->char('token', 10)->unique();
        $table->char('hash', 64);
        $table->json('track');
    });
    Schema::create('track_polygons', function (Blueprint $table): void {
        $table->id();
        $table->char('hash', 64)->unique();
        $table->json('polygon');
    });

    $this->trackJson = '{"type":"FeatureCollection","features":[]}';
    $this->polygonJson = '{"type":"Polygon","coordinates":[[[1,2],[3,4],[5,6],[1,2]]]}';
    DB::table('tracks')->insert([
        ['id' => 1, 'token' => 'TokenOne01', 'hash' => str_repeat('a', 64), 'track' => $this->trackJson],
        ['id' => 2, 'token' => 'TokenTwo02', 'hash' => str_repeat('a', 64), 'track' => $this->trackJson],
    ]);
    DB::table('track_polygons')->insert(['id' => 1, 'hash' => str_repeat('b', 64), 'polygon' => $this->polygonJson]);

    $this->migration = require database_path('migrations/2026_09_26_185755_move_track_geometry_to_files.php');
});

afterEach(function (): void {
    Schema::swap($this->originalSchema);
    DB::setDefaultConnection($this->originalConnection);
    DB::purge('track_files_migration');
});

test('track and polygon geometry moves to one file per row and the columns are dropped', function () {
    $this->migration->up();

    $disk = Storage::disk('tracks');
    expect($disk->get('uploads/TokenOne01.json'))->toBe($this->trackJson)
        ->and($disk->get('uploads/TokenTwo02.json'))->toBe($this->trackJson)
        ->and($disk->get('polygons/'.str_repeat('b', 64).'.json'))->toBe($this->polygonJson)
        ->and(Schema::hasColumn('tracks', 'track'))->toBeFalse()
        ->and(Schema::hasColumn('track_polygons', 'polygon'))->toBeFalse()
        ->and(DB::table('tracks')->count())->toBe(2);
});

test('rolling back restores the geometry columns from files and removes the files', function () {
    $this->migration->up();
    $this->migration->down();

    expect(DB::table('tracks')->where('id', 2)->value('track'))->toBe($this->trackJson)
        ->and(DB::table('track_polygons')->value('polygon'))->toBe($this->polygonJson)
        ->and(Storage::disk('tracks')->allFiles())->toBe([]);
});
