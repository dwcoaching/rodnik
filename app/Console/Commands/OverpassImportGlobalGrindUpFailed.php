<?php

namespace App\Console\Commands;

use App\Models\OverpassImport;
use Illuminate\Console\Command;

class OverpassImportGlobalGrindUpFailed extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'overpass:grind-up-failed-global';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Grind up those imports which have failed';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $dueImports = OverpassImport::whereNotNull('fetched_at')
            ->where('ground_up', false)
            ->where(function ($query) {
                return $query->where('response_code', '<>', 200)
                    ->orWhere('has_remarks', true);
            })
            ->get();

        foreach ($dueImports as $import) {
            $import->grindUp();
        }
    }
}
