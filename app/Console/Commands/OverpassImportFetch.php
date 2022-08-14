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
    protected $description = 'fetch an import with specified id';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $overpassImport = OverpassImport::findOrFail($this->argument('id'));

        $overpassImport->fetch();

        echo $overpassImport->response;
    }
}
