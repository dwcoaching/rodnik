<?php

namespace App\Console\Commands;

use GuzzleHttp\Client;
use Illuminate\Console\Command;

class TestOverpass extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'overpass:test';

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
        $guzzle = new Client;

        $result = $guzzle->request('GET', 'https://overpass-api.de/api/interpreter', [
            'query' => [
                'data' => "/*
                    This is an example Overpass query.
                    Try it out by pressing the Run button above!
                    You can find more examples with the Load tool.
                    */
                    node
                      [amenity=drinking_water]
                      (55,37,56,38);
                    out;"
            ]
        ]);

        echo $result->getBody();
    }
}
