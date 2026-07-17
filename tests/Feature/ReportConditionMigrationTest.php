<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('legacy report problem data is preserved while access values are merged', function () {
    $originalConnection = config('database.default');
    $connection = 'report_condition_migration_test';

    config([
        'database.default' => $connection,
        "database.connections.{$connection}" => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'foreign_key_constraints' => true,
        ],
    ]);

    DB::purge($connection);

    try {
        Schema::create('springs', function (Blueprint $table): void {
            $table->id();
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
        });

        Schema::create('reports', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('spring_id');
            $table->date('visited_at')->nullable();
            $table->string('comment')->nullable();
            $table->enum('access', ['limited', 'no'])->nullable();
            $table->boolean('ruined')->nullable();
        });

        foreach (['spring_tiles', 'watered_spring_tiles'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table): void {
                $table->id();
                $table->unsignedTinyInteger('z');
                $table->unsignedInteger('x');
                $table->unsignedInteger('y');
                $table->dateTime('generated_at')->nullable();
            });
        }

        DB::table('springs')->insert([
            ['id' => 101, 'latitude' => 10, 'longitude' => 10],
            ['id' => 102, 'latitude' => 0, 'longitude' => 0],
            ['id' => 103, 'latitude' => 0, 'longitude' => 0],
            ['id' => 104, 'latitude' => 10, 'longitude' => 10],
        ]);

        DB::table('reports')->insert([
            ['id' => 1, 'spring_id' => 101, 'visited_at' => '2026-07-01', 'comment' => 'Unreported', 'access' => null, 'ruined' => null],
            ['id' => 2, 'spring_id' => 102, 'visited_at' => '2026-07-02', 'comment' => 'Limited', 'access' => 'limited', 'ruined' => null],
            ['id' => 3, 'spring_id' => 103, 'visited_at' => '2026-07-03', 'comment' => 'No access', 'access' => 'no', 'ruined' => true],
            ['id' => 4, 'spring_id' => 104, 'visited_at' => null, 'comment' => 'Broken only', 'access' => null, 'ruined' => true],
        ]);

        foreach (['spring_tiles', 'watered_spring_tiles'] as $tableName) {
            DB::table($tableName)->insert([
                ['z' => 5, 'x' => 16, 'y' => 16, 'generated_at' => '2026-07-17 12:00:00'],
                ['z' => 5, 'x' => 1, 'y' => 1, 'generated_at' => '2026-07-17 12:00:00'],
            ]);
        }

        $migrations = [
            require database_path('migrations/2026_07_17_175227_add_access_limited_to_reports_table.php'),
            require database_path('migrations/2026_07_17_175230_backfill_report_access_limited.php'),
            require database_path('migrations/2026_07_17_175235_drop_access_from_reports_table.php'),
            require database_path('migrations/2026_07_17_175241_rename_ruined_to_broken_on_reports_table.php'),
            require database_path('migrations/2026_07_17_191313_invalidate_report_tiles_after_access_merge.php'),
        ];

        foreach ($migrations as $migration) {
            $migration->up();
        }

        expect(Schema::hasColumn('reports', 'access_limited'))->toBeTrue()
            ->and(Schema::hasColumn('reports', 'broken'))->toBeTrue()
            ->and(Schema::hasColumn('reports', 'access'))->toBeFalse()
            ->and(Schema::hasColumn('reports', 'ruined'))->toBeFalse()
            ->and(DB::table('spring_tiles')->where(['z' => 5, 'x' => 16, 'y' => 16])->value('generated_at'))->toBeNull()
            ->and(DB::table('watered_spring_tiles')->where(['z' => 5, 'x' => 16, 'y' => 16])->value('generated_at'))->toBeNull()
            ->and(DB::table('spring_tiles')->where(['z' => 5, 'x' => 1, 'y' => 1])->value('generated_at'))->toBe('2026-07-17 12:00:00')
            ->and(DB::table('watered_spring_tiles')->where(['z' => 5, 'x' => 1, 'y' => 1])->value('generated_at'))->toBe('2026-07-17 12:00:00')
            ->and(DB::table('reports')->orderBy('id')->get()->map(fn (object $report): array => [
                'id' => $report->id,
                'spring_id' => $report->spring_id,
                'visited_at' => $report->visited_at,
                'comment' => $report->comment,
                'access_limited' => $report->access_limited,
                'broken' => $report->broken,
            ])->all())->toBe([
                ['id' => 1, 'spring_id' => 101, 'visited_at' => '2026-07-01', 'comment' => 'Unreported', 'access_limited' => null, 'broken' => null],
                ['id' => 2, 'spring_id' => 102, 'visited_at' => '2026-07-02', 'comment' => 'Limited', 'access_limited' => 1, 'broken' => null],
                ['id' => 3, 'spring_id' => 103, 'visited_at' => '2026-07-03', 'comment' => 'No access', 'access_limited' => 1, 'broken' => 1],
                ['id' => 4, 'spring_id' => 104, 'visited_at' => null, 'comment' => 'Broken only', 'access_limited' => null, 'broken' => 1],
            ]);

        foreach (array_reverse($migrations) as $migration) {
            $migration->down();
        }

        expect(Schema::hasColumn('reports', 'access'))->toBeTrue()
            ->and(Schema::hasColumn('reports', 'ruined'))->toBeTrue()
            ->and(Schema::hasColumn('reports', 'access_limited'))->toBeFalse()
            ->and(Schema::hasColumn('reports', 'broken'))->toBeFalse()
            ->and(DB::table('reports')->orderBy('id')->pluck('access')->all())
            ->toBe([null, 'limited', 'limited', null])
            ->and(DB::table('reports')->orderBy('id')->pluck('ruined')->all())
            ->toBe([null, null, 1, 1]);
    } finally {
        DB::disconnect($connection);
        config(['database.default' => $originalConnection]);
        DB::purge($connection);
    }
});
