<?php

namespace App\Console\Commands;

use GuzzleHttp\Client;
use App\Models\OverpassImport;
use Illuminate\Console\Command;

class OverpassImportFetch extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'overpass:fetch {id}';

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
        $overpassImport = OverpassImport::findOrFail($this->argument('id'));

        $guzzle = new Client;

        $overpassImport->started_at = now();

        $area = '('
            . $overpassImport->latitude_from
            . ','
            . $overpassImport->longitude_from
            . ','
            . $overpassImport->latitude_to
            . ','
            . $overpassImport->longitude_to
            . ');';

        $query = "
            node
                [amenity=drinking_water]
                {$area}
            out;
            node
              [natural=spring]
              {$area}
            out;
            node
              [man_made=spring_box]
              {$area}
            out;
            node
              [man_made=water_well]
              {$area}
            out;
            node
              [amenity=fountain]
              {$area}
            out;
        ";

        $overpassImport->query = $query;

        $result = $guzzle->request('GET', 'https://overpass-api.de/api/interpreter', [
            'query' => [
                'data' => $query
            ]
        ]);

        $overpassImport->response = $result->getBody();
        $overpassImport->fetched_at = now();
        $overpassImport->save();

        echo $overpassImport->response;
    }
}
