<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\GeocodingRun;
use App\Services\Geocoding\BulkGeocoding;
use Illuminate\Console\Command;
use Throwable;

final class SpringsGeocode extends Command
{
    protected $signature = 'springs:geocode
        {--missing : Only sources without a country or city}
        {--reset : Clear existing geocoding and recalculate every source}
        {--queue : Process in the background using the configured geocoding queue}
        {--resume : Resume the current queued or interrupted run}
        {--run= : Continue an existing run ID (used by queue workers)}
        {--chunk : Process one bounded chunk (used by queue workers)}';

    protected $description = 'Attach country and nearest city to water sources using the local GeoJSON dataset';

    public function handle(BulkGeocoding $bulk): int
    {
        if ($this->option('missing') && $this->option('reset')) {
            $this->error('--missing and --reset cannot be used together.');

            return self::INVALID;
        }
        if (($this->option('resume') || $this->option('run')) && ($this->option('missing') || $this->option('reset'))) {
            $this->error('A resumed run retains its original mode.');

            return self::INVALID;
        }

        try {
            if ($this->option('run')) {
                $run = GeocodingRun::findOrFail($this->option('run'));
            } elseif ($this->option('resume')) {
                $run = GeocodingRun::whereNotNull('active_key')->first();
                if ($run === null) {
                    $this->error('There is no active reverse geocoding run to resume.');

                    return self::FAILURE;
                }
                if (! $run->canRecover()) {
                    $this->error('The current worker is still processing this run.');

                    return self::FAILURE;
                }
            } else {
                $mode = $this->option('missing') ? 'missing' : ($this->option('reset') ? 'reset' : 'all');
                $run = $bulk->start($mode);
            }

            if (! $run->isActive()) {
                $this->info('Reverse geocoding run #'.$run->id.' is '.$run->status.'.');

                return self::SUCCESS;
            }

            if ($this->option('queue')) {
                $bulk->enqueue($run);
                $this->info('Queued reverse geocoding run #'.$run->id.' ('.$run->total.' water sources).');

                return self::SUCCESS;
            }

            do {
                if (! $bulk->processChunk($run)) {
                    $this->warn('Run #'.$run->id.' is stopped or already being processed by another worker.');

                    return self::SUCCESS;
                }

                // Read persisted state: this chunk may have completed or stopped the run.
                $run = GeocodingRun::query()->findOrFail($run->id);

                $this->line('Run #'.$run->id.': '.$run->processed.'/'.$run->total
                    .' processed, '.$run->matched.' matched, '.$run->unmatched.' unmatched, '.$run->failed_count.' failed.');

                if ($this->option('chunk')) {
                    if ($run->isActive()) {
                        $bulk->enqueue($run);
                    }

                    return self::SUCCESS;
                }
            } while ($run->isActive());

            $this->info('Reverse geocoding completed.');
            if ($run->failed_count > 0) {
                $this->warn('Some sources failed. Run springs:geocode --missing to retry sources without a location.');
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
