<?php

namespace App\Console\Commands;

use App\Models\OverpassImport;
use Illuminate\Console\Command;

class OverpassImportGlobalCheck extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'overpass:check-global';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'check validity and completeness of all imports';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $dueImports = OverpassImport::whereNotNull('fetched_at')
            ->where('id', '>', 3900)
            //->where('response_code', 200)
            ->get();

        foreach ($dueImports as $import) {
            if ($import->responseHasRemarks()) {
                $import->has_remarks = true;
                $import->save();
                echo "Import id = {$import->id} has remarks\n";
            } else {
                $import->has_remarks = false;
                $import->save();
                echo "Import id = {$import->id} IS FUCKING PERFECT\n";
            }
        }
    }
}
