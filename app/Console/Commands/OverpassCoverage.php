<?php

namespace App\Console\Commands;

use App\Models\OverpassCheck;
use App\Models\OverpassImport;
use Illuminate\Console\Command;

class OverpassCoverage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'overpass:coverage';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'update coverage; accessible at rodnik.test/coverage';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // precheck stats

        echo "\nEXISTING STATISTICS:\n";
        echo $this->currentCoverageText();
        echo "\n\nNOW PROCEEDING TO CHECKING COVERAGE...\n";

        $overpassChecksUnknown = OverpassCheck::whereNull('covered_by')->get();

        foreach ($overpassChecksUnknown as $check) {
            echo $check->id . "\n";
            $overpassImport = OverpassImport::where('has_remarks', 0)
                ->where('latitude_from', '<=', $check->latitude_from)
                ->where('latitude_to', '>=', $check->latitude_to)
                ->where('longitude_from', '<=', $check->longitude_from)
                ->where('longitude_to', '>=', $check->longitude_to)
                ->first();

            if ($overpassImport) {
                $check->covered_by = $overpassImport->id;
                $check->save();
            }
        }

        echo "\n\nCOVERAGE STATISTICS UPDATED:\n";
        echo $this->currentCoverageText();
    }

    public function currentCoverageText()
    {
        $overpassChecksPositive = OverpassCheck::whereNotNull('covered_by')->get();
        $coverage = round($overpassChecksPositive->count() / 64800 * 100, 2);

        $result = "{$coverage}% of the globe is already covered\n({$overpassChecksPositive->count()} of 64800 tiles)\n";

        return $result;
    }
}
