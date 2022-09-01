<?php

namespace App\Console\Commands;

use App\Models\SpringTile;
use Illuminate\Console\Command;

class SpringTilesGlobalCreate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tiles:create-global';

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
        // zoom levels: 0, 5, 8
        $z = 8;

        $tileCount = pow(2, $z);

        for ($x = 0; $x < $tileCount; $x++) {
            for ($y = 0; $y < $tileCount; $y++) {
                $springTile = new SpringTile();
                $springTile->z = $z;
                $springTile->x = $x;
                $springTile->y = $y;
                $springTile->save();

                echo "Generating Spring Tile /{$z}/{$x}/{$y}/\n";
            }
        }
    }
}
