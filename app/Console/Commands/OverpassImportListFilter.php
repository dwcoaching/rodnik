<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class OverpassImportListFilter extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'overpass:list-filter';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Copy necessary imports into another directory + list.json, this generates
        a sealed version for the whole globe';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $json = json_decode(Storage::disk('local')->get('overpass/responses/list.json'));

        $date = now()->format('Y-m-d');

        foreach ($json as $import) {
            Storage::disk('local')->copy('overpass/responses/' . $import . '.json', 'overpass/sealed/' . $date . '/' . $import . '.json');
        }

        Storage::disk('local')->copy('overpass/responses/list.json', 'overpass/sealed/' . $date . '/list.json');
    }
}
