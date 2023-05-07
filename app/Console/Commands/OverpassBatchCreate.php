<?php

namespace App\Console\Commands;

use App\Models\OverpassBatch;
use Illuminate\Console\Command;

class OverpassBatchCreate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'overpass:batch';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $overpassBatch = new OverpassBatch();

        $overpassBatch->imports_status = ['not_started', 'queued', 'creating', 'created'];
        $overpassBatch->fetch_status = ['not_started', 'queued', 'fetching', 'fetched'];

        // for each step
        // filament — create, run actions $overpassBatch->generateImports
        // filament feels good
        // jobs
        // update statuses
        // go step by step

        OverpassImportGlobalCreate
        OverpassCoverage
        OverpassImportGlobalFetch
        OverpassImportGlobalCheck
        OverpassImportGlobalGrindUpFailed
        OverpassImportGlobalParse

        return Command::SUCCESS;
    }
}
