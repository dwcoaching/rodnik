<?php

namespace App\Console\Commands;

use App\Models\OverpassImport;
use Illuminate\Console\Command;

class OverpassImportGlobalParse extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'overpass:parse-global';

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
        $dueImports = OverpassImport::whereNotNull('fetched_at')
            ->whereNull('parsed_at')
            ->get();

        foreach ($dueImports as $dueImport) {
            echo "Parsing import id = {$dueImport->id}\n";
            $dueImport->parse();
        }
    }
}
