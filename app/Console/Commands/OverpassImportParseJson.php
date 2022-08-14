<?php

namespace App\Console\Commands;

use SimpleXMLElement;
use App\Models\OSMTag;
use App\Models\Spring;
use App\Models\OverpassImport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class OverpassImportParseJson extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'overpass:parse-json {id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Parse a JSON import with specified id';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $overpassImport = OverpassImport::findOrFail($this->argument('id'));

        $overpassImport->parse();
    }
}
