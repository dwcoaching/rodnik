<?php

namespace App\Jobs;

use App\Models\OverpassBatch;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;

class RemoveOlderOverpassArtifacts implements ShouldQueue
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
    public function handle()
    {
        $oldOverpassBatches = OverpassBatch::where('id', '<', $this->overpassBatch->id)->get();

        foreach ($oldOverpassBatches as $overpassBatch) {
            $overpassBatch->deleteWithArtifacts();
        }
    }
}
