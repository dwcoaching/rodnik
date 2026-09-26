<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('removing map tokens preserves existing links map data and view counts through rollback', function () {
    $originalConnection = config('database.default');
    $connection = 'map_token_migration_test';
    config([
        'database.default' => $connection,
        "database.connections.{$connection}" => [
            'driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true,
        ],
    ]);
    DB::purge($connection);

    try {
        Schema::create('maps', function (Blueprint $table): void {
            $table->id();
            $table->char('token', 8)->unique();
            $table->string('slug')->unique();
            $table->string('title');
            $table->json('state');
            $table->unsignedInteger('version');
            $table->unsignedBigInteger('views_count');
            $table->timestamps();
        });
        $rows = [
            ['id' => 1, 'token' => 'AbCd1234', 'slug' => 'old-route', 'title' => 'Named route', 'state' => '{"zoom":9.5}', 'version' => 3, 'views_count' => 42, 'created_at' => '2026-09-01 12:00:00', 'updated_at' => '2026-09-02 12:00:00'],
            ['id' => 2, 'token' => 'EfGh5678', 'slug' => 'EfGh5678', 'title' => 'Generated route', 'state' => '{"zoom":8.5}', 'version' => 7, 'views_count' => 10, 'created_at' => '2026-09-03 12:00:00', 'updated_at' => '2026-09-04 12:00:00'],
        ];
        DB::table('maps')->insert($rows);
        $expected = array_map(function (array $row): array {
            unset($row['token']);

            return $row;
        }, $rows);
        $migration = require database_path('migrations/2026_09_26_173335_remove_token_from_maps_table.php');

        $migration->up();

        expect(Schema::hasColumn('maps', 'token'))->toBeFalse()
            ->and(DB::table('maps')->orderBy('id')->get()->map(fn (object $map): array => (array) $map)->all())->toBe($expected);

        $migration->down();
        $restored = DB::table('maps')->orderBy('id')->get();
        expect(Schema::hasColumn('maps', 'token'))->toBeTrue()
            ->and($restored->pluck('token')->unique())->toHaveCount(2);
        foreach ($restored as $index => $map) {
            expect($map->token)->toMatch('/\A[a-zA-Z0-9]{8}\z/');
            $row = (array) $map;
            unset($row['token']);
            expect($row)->toBe($expected[$index]);
        }
        $migration->up();
        expect(Schema::hasColumn('maps', 'token'))->toBeFalse()
            ->and(DB::table('maps')->count())->toBe(2);
    } finally {
        DB::disconnect($connection);
        config(['database.default' => $originalConnection]);
        DB::purge($connection);
    }
});
