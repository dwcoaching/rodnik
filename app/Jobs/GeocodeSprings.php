<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\GeocodingRun;
use App\Services\Geocoding\BulkGeocoding;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;
use Throwable;

final class GeocodeSprings implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 60;

    public int $tries = 1;

    public bool $failOnTimeout = true;

    public function __construct(public int $runId) {}

    public function handle(): void
    {
        if (Artisan::call('springs:geocode', ['--run' => $this->runId, '--chunk' => true]) !== 0) {
            throw new RuntimeException(mb_trim(Artisan::output()));
        }
    }

    public function failed(?Throwable $exception): void
    {
        if ($run = GeocodingRun::find($this->runId)) {
            app(BulkGeocoding::class)->fail($run, $exception?->getMessage() ?? 'The reverse geocoding worker stopped.');
        }
    }
}
