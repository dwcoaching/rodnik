<?php

namespace App\Console\Commands;

use App\Models\OverpassImport;
use Illuminate\Console\Command;

class OverpassImportGlobalFetch extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'overpass:fetch-global';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch all unfetched imports';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $dueImports = OverpassImport::whereNull('fetched_at')->get();

        foreach ($dueImports as $dueImport) {
            echo "Fetching id = {$dueImport->id} ({$dueImport->latitude_from}, {$dueImport->longitude_from}) to ({$dueImport->latitude_to}, {$dueImport->longitude_to}) \n";
            $dueImport->fetch();
        }
    }
}
