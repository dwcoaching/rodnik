<?php

namespace App\Console\Commands;

use App\Models\SpringTile;
use Illuminate\Console\Command;

class SpringTilesGlobalGenerate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tiles:generate-global';

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
        $tiles = SpringTile::whereNull('generated_at')->get();

        foreach ($tiles as $tile) {
            echo "Generating File for Spring Tile /{$tile->z}/{$tile->x}/{$tile->y}/\n";
            $starttime = microtime(true);
            $tile->saveFile();
            $time = round(microtime(true) - $starttime, 2);
            echo "Generated in {$time}\n\n";
        }
    }
}
