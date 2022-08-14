<?php

namespace App\Console\Commands;

use App\Models\OverpassCheck;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class OverpassImportList extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'overpass:list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'create list.json that includes imports required for coverage';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $overpassImportIds = DB::table('overpass_checks')
            ->select('covered_by')
            ->distinct()
            ->get()
            ->pluck('covered_by');

        $json = json_encode($overpassImportIds);

        Storage::disk('local')->put('overpass/responses/list.json', $json);

        echo Storage::disk('local')->path('overpass/responses/list.json') . "\n";
    }
}
