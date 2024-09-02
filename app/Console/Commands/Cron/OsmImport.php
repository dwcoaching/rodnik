<?php

namespace App\Console\Commands\Cron;

use App\Models\OverpassBatch;
use Illuminate\Console\Command;
use App\Jobs\CreateOverpassBatchChecks;
use App\Jobs\FetchOverpassBatchImports;

class OsmImport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:osm-import';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start a full import from OSM';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $overpassBatch = new OverpassBatch();
        $overpassBatch->imports_status = 'not created';
        $overpassBatch->checks_status = 'not created';
        $overpassBatch->fetch_status = 'not started';
        $overpassBatch->parse_status = 'not started';
        $overpassBatch->save();
        $overpassBatch->createImports();

        CreateOverpassBatchChecks::dispatch($overpassBatch);
        FetchOverpassBatchImports::dispatch($overpassBatch);
    }
}
