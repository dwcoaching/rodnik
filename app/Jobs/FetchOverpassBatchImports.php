<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\OverpassBatch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class FetchOverpassBatchImports implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 0;

    /**
     * The worker runs with the default `--tries=1`, so the batch used to die on the first
     * unhandled error. A job property overrides that option, and the work is resumable:
     * every fetched import is recorded, so a retry picks up where the last attempt stopped.
     */
    public $tries = 50;

    public $maxExceptions = 50;

    public $backoff = [60, 300, 900];

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(
        public OverpassBatch $overpassBatch,
    ) {
        $this->onQueue('overpass');
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->overpassBatch->fetchImports();
    }
}
