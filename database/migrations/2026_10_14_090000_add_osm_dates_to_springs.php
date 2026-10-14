<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('springs', function (Blueprint $table): void {
            // When the object was last edited in OpenStreetMap, alongside osm_version.
            $table->dateTime('osm_timestamp')->nullable();

            // The state of OSM data in the last import that still contained the object.
            $table->dateTime('osm_last_seen_at')->nullable();

            // The first complete import that no longer contained it. Together with
            // osm_last_seen_at this brackets when the object was deleted or left our tags.
            $table->dateTime('osm_missing_since')->nullable();
        });

        $this->bracketObjectsAlreadyMissing();
    }

    public function down(): void
    {
        Schema::table('springs', function (Blueprint $table): void {
            $table->dropColumn(['osm_timestamp', 'osm_last_seen_at', 'osm_missing_since']);
        });
    }

    /**
     * Objects the latest parsed batch did not bring back were gone by the time that batch
     * started, which is a true if loose upper bound. The lower bound is only known where the
     * batch that last saw them is still on record: batches are deleted after every import, and
     * only their attempts survive.
     */
    private function bracketObjectsAlreadyMissing(): void
    {
        $latest = DB::table('overpass_batches')
            ->where('parse_status', 'parsed')
            ->orderByDesc('id')
            ->first(['id', 'created_at']);

        if (! $latest) {
            return;
        }

        // Read first and write by primary key: an UPDATE filtering on these columns would scan,
        // and lock, all 1.4 million springs to touch a couple of thousand.
        $missing = DB::table('springs')
            ->where(fn (Builder $query) => $query->whereNotNull('osm_node_id')->orWhereNotNull('osm_way_id'))
            ->where(fn (Builder $query) => $query
                ->whereNull('last_seen_overpass_batch_id')
                ->orWhere('last_seen_overpass_batch_id', '<', $latest->id))
            ->get(['id', 'last_seen_overpass_batch_id']);

        foreach ($missing->pluck('id')->chunk(1000) as $ids) {
            DB::table('springs')->whereIn('id', $ids)->update(['osm_missing_since' => $latest->created_at]);
        }

        $lastSeen = $missing->whereNotNull('last_seen_overpass_batch_id')->groupBy('last_seen_overpass_batch_id');

        $startedAt = DB::table('overpass_attempts')
            ->whereIn('overpass_batch_id', $lastSeen->keys())
            ->groupBy('overpass_batch_id')
            ->pluck(DB::raw('MIN(started_at)'), 'overpass_batch_id');

        foreach ($startedAt as $batchId => $seenAt) {
            DB::table('springs')
                ->whereIn('id', $lastSeen[$batchId]->pluck('id'))
                ->update(['osm_last_seen_at' => $seenAt]);
        }
    }
};
