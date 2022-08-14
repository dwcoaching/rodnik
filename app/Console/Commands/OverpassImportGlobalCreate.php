<?php

namespace App\Console\Commands;

use App\Models\OverpassImport;
use Illuminate\Console\Command;

class OverpassImportGlobalCreate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'overpass:create-global';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'create imports for the whole globe';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $latitudeStart = -90;
        $longitude = -180;

        // for ($latitude = -90; $latitude <= 80; $latitude = $latitude + 10) {
        //     for ($longitude = -180; $longitude <= 170; $longitude = $longitude + 10) {
        //         $overpassImport = new OverpassImport();
        //         $overpassImport->latitude_from = $latitude;
        //         $overpassImport->latitude_to = $latitude + 10;
        //         $overpassImport->longitude_from = $longitude;
        //         $overpassImport->longitude_to = $longitude + 10;
        //         $overpassImport->save();
        //     }
        // }

        for ($longitude = -180; $longitude <= 170; $longitude = $longitude + 10) {
            $overpassImport = new OverpassImport();
            $overpassImport->latitude_from = -90;
            $overpassImport->latitude_to = 90;
            $overpassImport->longitude_from = $longitude;
            $overpassImport->longitude_to = $longitude + 10;
            $overpassImport->save();
        }
    }
}
