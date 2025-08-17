<?php

namespace App\Console\Commands\Analytics;

use App\Models\Spring;
use Illuminate\Console\Command;

class ParseYandex extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:parse-yandex';

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
        $csv = file_get_contents(filename: resource_path('analytics/yandex-source.csv'));
    
        // Parse CSV content into array
        $csv_lines = str_getcsv($csv, "\n");

        $results = [];
        
        foreach ($csv_lines as $line) {
            if (!empty(trim($line))) {
                $line = str_getcsv($line);
                
                $url = $line[0];
                $views = intval($line[1]);
                $visitors = intval($line[2]);
                $springId = null;

                // Pattern 1: page[spring]=953578
                if (preg_match('/page\[spring\]=(\d+)/', $url, $matches)) {
                    $springId = $matches[1];
                }
                // Pattern 2: https://rodnik.today/440816 (direct spring ID)
                elseif (preg_match('/rodnik\.today\/(\d+)$/', $url, $matches)) {
                    $springId = $matches[1];
                }
                // Pattern 3: https://rodnik.today/?s=1133568
                elseif (preg_match('/\?s=(\d+)/', $url, $matches)) {
                    $springId = $matches[1];
                }

                if (!$springId) {
                    continue;
                }
                
                // If we found a spring ID, store the data
                if (array_key_exists($springId, $results)) {
                    $results[$springId]['views'] += $views;
                    $results[$springId]['visitors'] += $visitors;
                } else {
                    $results[$springId] = [
                        'spring_id' => $springId,
                        'views' => $views,
                        'visitors' => $visitors,
                    ];
                }
            }
        }

        
        $springs = Spring::whereIn('id', array_keys($results))->get();

        $results_with_coordinates = [];

        foreach ($springs as $spring) {
            $results_with_coordinates[$spring->id] = $results[$spring->id];
            $results_with_coordinates[$spring->id]['latitude'] = $spring->latitude;
            $results_with_coordinates[$spring->id]['longitude'] = $spring->longitude;
        }

        usort($results_with_coordinates, function($a, $b) {
            return $b['views'] - $a['views'];
        });

        $json = json_encode($results_with_coordinates);
        file_put_contents(filename: public_path('analytics/yandex-results.json'), data: $json);
    }
}
