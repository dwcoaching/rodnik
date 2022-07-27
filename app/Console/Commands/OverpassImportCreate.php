<?php

namespace App\Console\Commands;

use App\Models\OverpassImport;
use Illuminate\Console\Command;

class OverpassImportCreate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'overpass:create';

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
        $overpassImport = new OverpassImport();

        $overpassImport->latitude_from = 39;
        $overpassImport->latitude_to = 40;
        $overpassImport->longitude_from = 3;
        $overpassImport->longitude_to = 4;

        $overpassImport->save();
        echo "OverpassImport Id = {$overpassImport->id}\n";
    }
}
