<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\OverpassBatch;
use App\Models\Spring;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class PruneMissingOSMSprings implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 0;

    public $overpassBatch = null;

    public function __construct(?OverpassBatch $overpassBatch = null)
    {
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

        $this->markMissing();

        // Hidden springs are kept. Hiding is a decision taken on Rodnik and recorded nowhere else:
        // if the object ever comes back to OSM, it has to come back hidden.
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
            }
        }
    }

    /**
     * Note when OSM objects first went missing, hidden ones included, for the ones that are
     * kept. A complete batch that did not bring an object back means it was gone by the time
     * the batch started; an object already marked keeps its earlier, tighter date.
     */
    private function markMissing(): void
    {
        Spring::query()
            ->where(function ($q) {
                $q->whereNotNull('osm_node_id')->orWhereNotNull('osm_way_id');
            })
            ->where(function ($q) {
                $q->whereNull('last_seen_overpass_batch_id')
                    ->orWhere('last_seen_overpass_batch_id', '<', $this->overpassBatch->id);
            })
            ->whereNull('osm_missing_since')
            ->toBase()
            ->update(['osm_missing_since' => $this->overpassBatch->created_at]);
    }
}
