<?php

declare(strict_types=1);

use App\Contracts\ReverseGeocoder;
use App\Filament\Pages\Geocoding;
use App\Jobs\GeocodeSprings;
use App\Models\GeocodingRun;
use App\Models\Spring;
use App\Models\User;
use App\Services\Geocoding\BulkGeocoding;
use App\Services\Geocoding\GeocodedLocation;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

final class RecordingBulkGeocoder implements ReverseGeocoder
{
    public bool $broken = false;

    public array $lookups = [];

    public array $failedLatitudes = [];

    public ?Closure $duringLookup = null;

    public function validateSource(): void
    {
        if ($this->broken) {
            throw new RuntimeException('Invalid bulk geocoding dataset');
        }
    }

    public function reverse(float $latitude, float $longitude): ?GeocodedLocation
    {
        $this->validateSource();
        $this->lookups[] = [$latitude, $longitude];
        ($this->duringLookup ?? static fn () => null)();
        if (in_array($latitude, $this->failedLatitudes, true)) {
            throw new RuntimeException('Lookup failed');
        }

        return $latitude < 0 ? null : catalogLocation('AM', 'Yerevan', region: 'Yerevan');
    }
}

function bulkGeocodingSpring(array $attributes = []): Spring
{
    return Spring::withoutEvents(fn () => Spring::factory()->create([
        'latitude' => 40.18, 'longitude' => 44.51, ...$attributes,
    ]));
}

beforeEach(function () {
    app()->instance(ReverseGeocoder::class, $this->geocoder = new RecordingBulkGeocoder);
    config(['geocoding.queue_connection' => 'database', 'geocoding.queue' => 'default', 'geocoding.chunk_size' => 200]);
    Queue::fake();
});

test('the bulk command geocodes all water sources including hidden and redirected ones', function () {
    $first = bulkGeocodingSpring();
    $hidden = bulkGeocodingSpring(['hidden_at' => now()]);
    $redirected = bulkGeocodingSpring(['redirect_to_spring_id' => $first->id]);

    $this->artisan('springs:geocode')->assertSuccessful();

    foreach ([$first, $hidden, $redirected] as $spring) {
        expect($spring->fresh()->geoCountry->code)->toBe('AM')->and($spring->fresh()->nearestCity->name)->toBe('Yerevan');
    }
    $run = GeocodingRun::latest('id')->firstOrFail();
    expect($run->status)->toBe('completed')->and($run->processed)->toBe(3)
        ->and($run->matched)->toBe(3)->and($run->active_key)->toBeNull()
        ->and($run->progressPercentage())->toBe(100);
});

test('missing mode includes a missing country or city link but preserves complete locations', function () {
    $saved = catalogLinks('ZZ', 'Saved city');
    $complete = bulkGeocodingSpring($saved);
    $countryMissing = bulkGeocodingSpring([...$saved, 'country_id' => null]);
    $cityMissing = bulkGeocodingSpring([...$saved, 'nearest_city_id' => null]);

    $this->artisan('springs:geocode', ['--missing' => true])->assertSuccessful();

    expect($complete->fresh()->geoCountry->code)->toBe('ZZ')->and($complete->fresh()->nearestCity->name)->toBe('Saved city')
        ->and($countryMissing->fresh()->nearestCity->name)->toBe('Yerevan')
        ->and($cityMissing->fresh()->geoCountry->code)->toBe('AM')
        ->and($this->geocoder->lookups)->toHaveCount(2);
});

test('reset clears all derived fields and recalculates after validating the dataset', function () {
    $old = catalogLinks('ZZ', 'Old', 'Old');
    $matched = bulkGeocodingSpring($old);
    $unmatched = bulkGeocodingSpring([
        'latitude' => -10, ...$old, 'nearest_city_distance_km' => 5, 'geocoding_source' => 'Old', 'geocoded_at' => now(),
    ]);

    $this->artisan('springs:geocode', ['--reset' => true])->assertSuccessful();

    expect($matched->fresh()->geoCountry->code)->toBe('AM')->and($matched->fresh()->geoRegion->name)->toBe('Yerevan');
    foreach (['country_id', 'region_id', 'nearest_city_id', 'nearest_city_distance_km', 'geocoding_source'] as $field) {
        expect($unmatched->fresh()->{$field})->toBeNull();
    }
    $run = GeocodingRun::latest('id')->firstOrFail();
    expect($run->reset_applied_at)->not->toBeNull()->and($run->matched)->toBe(1)->and($run->unmatched)->toBe(1);
});

test('an invalid dataset never clears existing geocoding during reset', function () {
    $spring = bulkGeocodingSpring(catalogLinks('ZZ', 'Saved city'));
    $this->geocoder->broken = true;

    $this->artisan('springs:geocode', ['--reset' => true])->assertFailed();

    expect($spring->fresh()->geoCountry->code)->toBe('ZZ')->and($spring->fresh()->nearestCity->name)->toBe('Saved city')
        ->and(GeocodingRun::count())->toBe(0);
});

test('a stale reset run instance cannot reset locations a second time', function () {
    $spring = bulkGeocodingSpring(catalogLinks('ZZ', 'Old'));
    $run = app(BulkGeocoding::class)->start('reset');
    $run->reset_applied_at = null;
    $spring->refresh();
    Illuminate\Support\Facades\DB::table('springs')->where('id', $spring->id)
        ->update(catalogLinks('YY', 'Saved concurrently'));
    $this->geocoder->broken = false;
    $this->geocoder->failedLatitudes = [40.18];

    app(BulkGeocoding::class)->processChunk($run);

    expect($spring->fresh()->geoCountry->code)->toBe('YY')
        ->and($spring->fresh()->nearestCity->name)->toBe('Saved concurrently')
        ->and($run->reset_applied_at)->not->toBeNull();
});

test('queued runs use the background connection and continue through bounded chunks with durable progress', function () {
    bulkGeocodingSpring();
    bulkGeocodingSpring();
    config(['queue.default' => 'sync', 'geocoding.chunk_size' => 1]);

    $this->artisan('springs:geocode', ['--queue' => true])->assertSuccessful();
    $run = GeocodingRun::latest('id')->firstOrFail();
    expect($run->status)->toBe('queued')->and($run->processed)->toBe(0);
    Queue::assertPushed(GeocodeSprings::class, fn (GeocodeSprings $job) => $job->connection === 'database' && $job->runId === $run->id);

    (new GeocodeSprings($run->id))->handle();
    expect($run->fresh()->status)->toBe('queued')->and($run->fresh()->processed)->toBe(1)
        ->and($run->fresh()->processing_token)->toBeNull();
    Queue::assertPushed(GeocodeSprings::class, 2);

    (new GeocodeSprings($run->id))->handle();
    expect($run->fresh()->status)->toBe('completed')->and($run->fresh()->processed)->toBe(2)
        ->and($run->fresh()->matched)->toBe(2)->and($run->fresh()->active_key)->toBeNull();
});

test('another CLI or UI request cannot reserve a concurrent bulk run', function () {
    bulkGeocodingSpring();
    $this->artisan('springs:geocode', ['--queue' => true])->assertSuccessful();
    $this->artisan('springs:geocode', ['--missing' => true])->assertFailed();

    expect(GeocodingRun::count())->toBe(1);
    Queue::assertPushed(GeocodeSprings::class, 1);
});

test('a duplicate worker cannot claim a chunk that is currently processing', function () {
    bulkGeocodingSpring();
    $bulk = app(BulkGeocoding::class);
    $run = $bulk->start('all');
    $this->geocoder->duringLookup = static function () use ($bulk, $run): void {
        expect($bulk->processChunk($run->fresh()))->toBeFalse();
    };

    expect($bulk->processChunk($run))->toBeTrue()->and($run->processed)->toBe(1)->and($run->matched)->toBe(1);
});

test('a stopped stale worker cannot overwrite locations saved by a replacement reset run', function () {
    $spring = bulkGeocodingSpring();
    $bulk = app(BulkGeocoding::class);
    $run = $bulk->start('all');
    $replacement = null;
    $this->geocoder->duringLookup = function () use ($bulk, $run, $spring, &$replacement): void {
        $this->geocoder->duringLookup = null;
        $run->update(['lease_until' => now()->subSecond()]);
        $bulk->stop($run);
        $replacement = $bulk->start('reset');
        Illuminate\Support\Facades\DB::table('springs')->where('id', $spring->id)->update(catalogLinks('ZZ', 'Replacement city'));
    };

    expect($bulk->processChunk($run))->toBeFalse()
        ->and($spring->fresh()->geoCountry->code)->toBe('ZZ')
        ->and($spring->fresh()->nearestCity->name)->toBe('Replacement city')
        ->and($run->fresh()->status)->toBe('failed')
        ->and($replacement?->fresh()->isActive())->toBeTrue();
});

test('lookup failures are counted without preventing later sources from being processed', function () {
    bulkGeocodingSpring(['latitude' => 30]);
    bulkGeocodingSpring(['latitude' => 40]);
    $this->geocoder->failedLatitudes = [30.0];

    $this->artisan('springs:geocode')->assertSuccessful();

    $run = GeocodingRun::latest('id')->firstOrFail();
    expect($run->status)->toBe('completed')->and($run->processed)->toBe(2)
        ->and($run->matched)->toBe(1)->and($run->failed_count)->toBe(1)->and($run->error)->toContain('Lookup failed');
});

test('stale worker runs can be resumed or released but live workers cannot be stopped', function () {
    bulkGeocodingSpring();
    $bulk = app(BulkGeocoding::class);
    $run = $bulk->start('all');
    $run->update(['status' => 'running', 'processing_token' => (string) Illuminate\Support\Str::uuid(), 'lease_until' => now()->addMinute()]);

    expect(fn () => $bulk->stop($run))->toThrow(RuntimeException::class, 'currently processing');
    $this->artisan('springs:geocode', ['--queue' => true, '--resume' => true])->assertFailed();

    $run->update(['lease_until' => now()->subSecond()]);
    $this->artisan('springs:geocode', ['--resume' => true])->assertSuccessful();
    expect($run->fresh()->status)->toBe('completed');

    $queued = $bulk->start('all');
    $bulk->stop($queued);
    expect($queued->fresh()->status)->toBe('failed')->and($queued->fresh()->active_key)->toBeNull();
});

test('job failure marks the run failed and releases the reservation', function () {
    bulkGeocodingSpring();
    $run = app(BulkGeocoding::class)->start('all');

    (new GeocodeSprings($run->id))->failed(new RuntimeException('Worker timed out'));

    expect($run->fresh()->status)->toBe('failed')->and($run->fresh()->active_key)->toBeNull()
        ->and($run->fresh()->error)->toBe('Worker timed out');
});

test('the admin page starts the CLI and exposes saved progress while ordinary users are denied', function () {
    bulkGeocodingSpring();
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    Livewire::test(Geocoding::class)->call('startRun', 'missing')->assertSee('Waiting for a queue worker.');
    Queue::assertPushed(GeocodeSprings::class, 1);

    $this->actingAs(User::factory()->create(['is_admin' => false, 'is_superadmin' => false]));
    Livewire::test(Geocoding::class)->assertForbidden();
    expect(Geocoding::canAccess())->toBeFalse();
});

test('conflicting CLI modes fail before any locations are changed', function () {
    $spring = bulkGeocodingSpring(catalogLinks('ZZ', 'Saved city'));

    expect(Artisan::call('springs:geocode', ['--missing' => true, '--reset' => true]))->toBe(2)
        ->and($spring->fresh()->nearestCity->name)->toBe('Saved city')->and(GeocodingRun::count())->toBe(0);
});
