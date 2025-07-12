<?php

namespace App\Console\Commands\GPX;

use App\Models\Spring;
use App\Library\EnrichGPX;
use Illuminate\Console\Command;

class ExportEnrich extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:export-enrich';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $fileName = 'gpx/karia-yolu.gpx';

        // read storage/gpx/karia-yolu.gpx
        $gpx = file_get_contents(storage_path($fileName));

        $gpxString = EnrichGPX::enrich($gpx);
        $enrichedFileName = preg_replace('/(\.gpx)$/', '-enriched$1', $fileName);
        file_put_contents(storage_path($enrichedFileName), $gpxString);

        echo "GPX file enriched and saved as {$enrichedFileName}\n";
    }
}
