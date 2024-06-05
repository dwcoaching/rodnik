<?php

namespace App\Jobs;

use App\Library\Laundry;
use App\Models\OverpassBatch;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;

class CleanupOSMSprings implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 0;

    public $overpassBatch = null;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(?OverpassBatch $overpassBatch = null) {
        $this->overpassBatch = $overpassBatch;
        $this->onQueue('overpass');
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(Laundry $laundry)
    {
        if ($this->overpassBatch) {
            $this->overpassBatch->cleanup_status = 'started';
            $this->overpassBatch->save();
        }

        $laundry->cleanup();

        if ($this->overpassBatch) {
            $this->overpassBatch->cleanup_status = 'completed';
            $this->overpassBatch->save();
        }
    }
}
