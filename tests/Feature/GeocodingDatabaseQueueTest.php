<?php

declare(strict_types=1);

use App\Contracts\ReverseGeocoder;
use App\Filament\Pages\Geocoding;
use App\Jobs\GeocodeSprings;
use App\Models\GeocodingRun;
use App\Models\Spring;
use App\Models\User;
use App\Services\Geocoding\GeoJsonReverseGeocoder;
use Illuminate\Queue\DatabaseQueue;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->originalQueueDatabase = DB::getDefaultConnection();
    $this->originalQueueSchema = Schema::getFacadeRoot();
    $this->queueDatabasePath = tempnam(sys_get_temp_dir(), 'rodnik-queue-db-');
    $this->queueDatasetPath = tempnam(sys_get_temp_dir(), 'rodnik-queue-geojson-');
    $this->queueConnectionName = 'geocoding_queue_integration';
    $this->queueName = 'geocoding-integration-only';
    config([
        'database.connections.geocoding_queue_sqlite' => [
            'driver' => 'sqlite', 'database' => $this->queueDatabasePath,
            'prefix' => '', 'foreign_key_constraints' => true,
        ],
        'queue.connections.'.$this->queueConnectionName => [
            'driver' => 'database', 'connection' => 'geocoding_queue_sqlite',
            'table' => 'jobs', 'queue' => $this->queueName,
            'retry_after' => 90, 'after_commit' => false,
        ],
        'queue.failed.database' => 'geocoding_queue_sqlite',
        'geocoding.path' => $this->queueDatasetPath,
        'geocoding.queue_connection' => $this->queueConnectionName,
        'geocoding.queue' => $this->queueName,
        'geocoding.chunk_size' => 1,
    ]);
    DB::purge('geocoding_queue_sqlite');
    DB::setDefaultConnection('geocoding_queue_sqlite');
    Schema::swap(DB::connection()->getSchemaBuilder());

    // Use real schema and a dedicated persistent SQLite file. No application
    // database or unrelated queue is read, migrated, or consumed by this test.
    foreach ([
        CreateUsersTable::class => '2014_10_12_000000_create_users_table.php',
        CreateSpringsTable::class => '2021_10_27_212440_create_springs_table.php',
        CreateFailedJobsTable::class => '2019_08_19_000000_create_failed_jobs_table.php',
    ] as $class => $migration) {
        require_once database_path('migrations/'.$migration);
        (new $class)->up();
    }
    foreach ([
        '2022_09_30_200357_create_jobs_table.php',
        '2023_05_21_094022_update_users_add_is_admin.php',
        '2023_06_11_221848_update_users_add_rating.php',
        '2023_11_12_150944_update_user_add_superadmin.php',
        '2024_06_01_133236_update_springs_add_hidden_at.php',
        '2026_04_27_000001_update_springs_add_redirect_to_spring_id.php',
        '2026_10_02_000002_create_geocoding_runs_table.php',
        '2026_10_02_090000_add_geocoding_to_springs_table.php',
        '2026_10_02_100000_create_geography_catalog_tables.php',
        '2026_10_02_110000_link_springs_to_geography.php',
    ] as $migration) {
        (require database_path('migrations/'.$migration))->up();
    }

    $this->queueFeatures = [
        [
            'type' => 'Feature',
            'properties' => ['country' => 'Exampleland', 'country_code' => 'AA'],
            'geometry' => ['type' => 'Polygon', 'coordinates' => [[[0, 0], [10, 0], [10, 10], [0, 10], [0, 0]]]],
        ],
        [
            'type' => 'Feature',
            'properties' => ['city' => 'Westville', 'country_code' => 'AA', 'region' => 'West'],
            'geometry' => ['type' => 'Point', 'coordinates' => [1, 1]],
        ],
        [
            'type' => 'Feature',
            'properties' => ['city' => 'Eastville', 'country_code' => 'AA', 'region' => 'East'],
            'geometry' => ['type' => 'Point', 'coordinates' => [9, 9]],
        ],
    ];
    file_put_contents($this->queueDatasetPath, json_encode([
        'type' => 'FeatureCollection', 'features' => $this->queueFeatures,
    ], JSON_THROW_ON_ERROR));
    app()->instance(ReverseGeocoder::class, new GeoJsonReverseGeocoder(app('config')));

    // Raw inserts leave locations missing until the actual serialized jobs run.
    DB::table('springs')->insert([
        ['id' => 1, 'latitude' => 1.4, 'longitude' => 1.2, 'country' => null, 'city' => null],
        ['id' => 2, 'latitude' => 8.6, 'longitude' => 8.8, 'country' => null, 'city' => null],
        ['id' => 3, 'latitude' => 2.0, 'longitude' => 1.8, 'country' => 'Saved country', 'city' => 'Saved city'],
        ['id' => 4, 'latitude' => 5.0, 'longitude' => 12.0, 'country' => 'Saved country', 'city' => 'Saved city'],
    ]);
    DB::table('jobs')->insert([
        'queue' => 'unrelated-integration-sentinel', 'payload' => '{}',
        'attempts' => 0, 'reserved_at' => null, 'available_at' => time(), 'created_at' => time(),
    ]);
    $this->processedQueueJobs = [];
    Event::listen(JobProcessed::class, function (JobProcessed $event): void {
        $this->processedQueueJobs[] = $event->job->resolveName();
    });
    $this->actingAs(User::factory()->create(['is_admin' => true]));
});

afterEach(function (): void {
    Schema::swap($this->originalQueueSchema);
    DB::setDefaultConnection($this->originalQueueDatabase);
    DB::purge('geocoding_queue_sqlite');
    unlink($this->queueDatabasePath);
    unlink($this->queueDatasetPath);
});

test('filament commands run through the real database queue with progress, dataset replacement, and reset confirmation', function (): void {
    expect(Queue::connection($this->queueConnectionName))->toBeInstanceOf(DatabaseQueue::class);
    $page = Livewire::test(Geocoding::class);
    $page->callAction('missing')->assertSee('Waiting for a queue worker.');

    $missing = GeocodingRun::latest('id')->firstOrFail();
    $payload = json_decode(DB::table('jobs')->where('queue', $this->queueName)->value('payload'), true, flags: JSON_THROW_ON_ERROR);
    expect($missing->status)->toBe('queued')->and($missing->total)->toBe(2)
        ->and($missing->processed)->toBe(0)
        ->and($payload['data']['commandName'])->toBe(GeocodeSprings::class)
        ->and($payload['data']['command'])->toContain('runId');

    expect(Artisan::call('queue:work', [
        'connection' => $this->queueConnectionName, '--queue' => $this->queueName,
        '--stop-when-empty' => true, '--sleep' => 0, '--max-jobs' => 1,
    ]))->toBe(0);
    $missing->refresh();
    expect($missing->status)->toBe('queued')->and($missing->processed)->toBe(1)
        ->and($missing->progressPercentage())->toBe(50)
        ->and(DB::table('jobs')->where('queue', $this->queueName)->count())->toBe(1)
        ->and(Spring::findOrFail(1)->city)->toBe('Westville')
        ->and(Spring::findOrFail(2)->city)->toBeNull();
    $page->call('$refresh')->assertSee('1 of 2 processed');

    expect(Artisan::call('queue:work', [
        'connection' => $this->queueConnectionName, '--queue' => $this->queueName,
        '--stop-when-empty' => true, '--sleep' => 0,
    ]))->toBe(0);
    expect($missing->fresh()->status)->toBe('completed')->and($missing->fresh()->processed)->toBe(2)
        ->and(Spring::findOrFail(2)->city)->toBe('Eastville')
        ->and(Spring::findOrFail(3)->city)->toBe('Saved city');

    // Recalculate-all must observe a replacement inside the same long-lived
    // application/worker, including sources that already have country and city.
    $this->queueFeatures[1]['properties']['city'] = 'Westfield';
    file_put_contents($this->queueDatasetPath, json_encode([
        'type' => 'FeatureCollection', 'features' => $this->queueFeatures,
    ], JSON_THROW_ON_ERROR));
    $page->callAction('all');
    $all = GeocodingRun::latest('id')->firstOrFail();
    expect($all->mode)->toBe('all')->and($all->total)->toBe(4);
    expect(Artisan::call('queue:work', [
        'connection' => $this->queueConnectionName, '--queue' => $this->queueName,
        '--stop-when-empty' => true, '--sleep' => 0,
    ]))->toBe(0);
    expect($all->fresh()->status)->toBe('completed')->and($all->fresh()->matched)->toBe(3)
        ->and($all->fresh()->unmatched)->toBe(1)
        ->and(Spring::findOrFail(1)->city)->toBe('Westfield')
        ->and(Spring::findOrFail(3)->city)->toBe('Westfield')
        ->and(Spring::findOrFail(4)->country)->toBeNull();

    // Opening the confirmation has no writes. Only submitting it invokes the
    // shared reset CLI and queues the recalculation.
    $page->mountAction('reset')->assertActionMounted('reset');
    expect(GeocodingRun::count())->toBe(2)->and(Spring::findOrFail(1)->city)->toBe('Westfield');
    $page->callMountedAction();
    $reset = GeocodingRun::latest('id')->firstOrFail();
    expect($reset->mode)->toBe('reset')->and($reset->reset_applied_at)->not->toBeNull()
        ->and($reset->status)->toBe('queued')
        ->and(DB::table('springs')->whereNotNull('country')->count())->toBe(0)
        ->and(DB::table('springs')->whereNotNull('city')->count())->toBe(0);
    expect(Artisan::call('queue:work', [
        'connection' => $this->queueConnectionName, '--queue' => $this->queueName,
        '--stop-when-empty' => true, '--sleep' => 0,
    ]))->toBe(0);
    expect($reset->fresh()->status)->toBe('completed')->and($reset->fresh()->processed)->toBe(4)
        ->and($reset->fresh()->matched)->toBe(3)->and($reset->fresh()->unmatched)->toBe(1)
        ->and(Spring::findOrFail(1)->country_code)->toBe('AA')
        ->and(Spring::findOrFail(2)->region)->toBe('East')
        ->and($this->processedQueueJobs)->toBe(array_fill(0, 10, GeocodeSprings::class))
        ->and(DB::table('jobs')->where('queue', $this->queueName)->count())->toBe(0)
        ->and(DB::table('failed_jobs')->count())->toBe(0)
        ->and(DB::table('jobs')->where('queue', 'unrelated-integration-sentinel')->count())->toBe(1);
    $page->call('$refresh')->assertSee('4 water sources processed')->assertSee('Completed');
});
