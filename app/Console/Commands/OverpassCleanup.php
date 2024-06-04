<?php

namespace App\Console\Commands;

use App\Library\Laundry;
use App\Jobs\CleanupOSMSprings;
use Illuminate\Console\Command;

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
    public function handle()
    {
        CleanupOSMSprings::dispatch();
    }
}
