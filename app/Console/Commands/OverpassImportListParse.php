<?php

namespace App\Console\Commands;

use App\Library\Overpass;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class OverpassImportListParse extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'overpass:list-parse';

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
        $date = '2022-08-03';

        $json = json_decode(Storage::disk('local')->get('overpass/sealed/' . $date .'/list.json'));

        $count = count($json);

        $starttime = microtime(true);
        $total = 0;

        foreach ($json as $i => $import) {
            $response = Storage::disk('local')->get('overpass/sealed/' . $date . '/' . $import . '.json');

            echo 'PARSING ' . $i + 1 . ' of ' . $count . "\n";

            $stats = Overpass::parse(json_decode($response));
            echo 'new: ' . $stats->new . "\n";
            echo 'existing: ' . $stats->existing . "\n";

            $total = $total + $stats->new + $stats->existing;
            echo 'speed: ' . round($total / (microtime(true) - $starttime), 2) . ' per second' . "\n\n";
        }
    }
}
