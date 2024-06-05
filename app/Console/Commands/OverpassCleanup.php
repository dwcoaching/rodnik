<?php

namespace App\Console\Commands;

use App\Models\Spring;
use App\Library\Laundry;
use App\Models\OverpassBatch;
use App\Jobs\CleanupOSMSprings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Collection;

class OverpassCleanup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'overpass:cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Starts OSM Spring Cleanup. Can be started from Filament also.';

    /**
     * Execute the console command.
     */
    public function handle(Laundry $laundry)
    {
        // CleanupOSMSprings::dispatch();

        //echo $laundry->getCleaningQuery()->toRawSql();

        $springs = Spring::whereNotNull('hidden_at')
            ->where(function ($query) {
                $query->whereExists(function ($subQuery) {
                    $subQuery->select(DB::raw(1))
                        ->from('osm_tags')
                        ->whereColumn('springs.id', 'osm_tags.spring_id')
                        ->where('key', 'amenity')
                        ->where('value', 'fountain');
                })
                ->whereExists(function ($subQuery) {
                    $subQuery->select(DB::raw(1))
                        ->from('osm_tags')
                        ->whereColumn('springs.id', 'osm_tags.spring_id')
                        ->where('key', 'drinking_water')
                        ->where('value', 'no');
                });
            })
            ->where('hidden_at', '>=', '2024-06-04 19:00:00')
            ->where('hidden_at', '<', '2024-06-05 01:00:00')
            ->chunkById(100, function (Collection $springs) {
                foreach ($springs as $spring) {
                    echo $spring->id . "\n";
                    $spring->unhide();
                }
            });
    }
}
