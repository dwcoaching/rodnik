<?php

namespace App\Jobs;

use App\Models\OverpassBatch;
use App\Models\Spring;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class PruneMissingOSMSprings implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 0;

    public $overpassBatch = null;

    public function __construct(?OverpassBatch $overpassBatch = null) {
        $this->overpassBatch = $overpassBatch;
        $this->onQueue('overpass');
    }

    public function handle()
    {
        if (! $this->overpassBatch) {
            return;
        }

        if ($this->overpassBatch->parse_status !== 'parsed') {
            return;
        }

        if ((int) $this->overpassBatch->coverage !== 100) {
            return;
        }

        $candidates = Spring::query()
            ->whereNull('hidden_at')
            ->where(function ($q) {
                $q->whereNotNull('osm_node_id')->orWhereNotNull('osm_way_id');
            })
            ->where(function ($q) {
                $q->whereNull('last_seen_overpass_batch_id')
                  ->orWhere('last_seen_overpass_batch_id', '<', $this->overpassBatch->id);
            })
            ->get();

        foreach ($candidates as $spring) {
            if ($spring->canBePrunedAsMissing()) {
                $spring->pruneAsMissing();
            } else {
                $spring->hide();
            }
        }
    }
}
