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

        // mallorca
        // $overpassImport->latitude_from = 39;
        // $overpassImport->latitude_to = 40;
        // $overpassImport->longitude_from = 2.5;
        // $overpassImport->longitude_to = 3.5;

        // crimea
        // $overpassImport->latitude_from = 44;
        // $overpassImport->latitude_to = 45;
        // $overpassImport->longitude_from = 34;
        // $overpassImport->longitude_to = 35;

        // // moscow
        // $overpassImport->latitude_from = 55;
        // $overpassImport->latitude_to = 56;
        // $overpassImport->longitude_from = 37;
        // $overpassImport->longitude_to = 38;

        // georgia + turkey
        $overpassImport->latitude_from = 38;
        $overpassImport->latitude_to = 43;
        $overpassImport->longitude_from = 26;
        $overpassImport->longitude_to = 45;

        $overpassImport->save();
        echo "OverpassImport Id = {$overpassImport->id}\n";
    }
}
