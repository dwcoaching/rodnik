<?php

namespace App\Console\Commands;

use App\Models\SpringTile;
use Illuminate\Console\Command;
use App\Models\WateredSpringTile;

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
        $zoom = collect([0, 5, 8]);

        $zoom->each(function ($z) {
            $tileCount = pow(2, $z);

            for ($x = 0; $x < $tileCount; $x++) {
                for ($y = 0; $y < $tileCount; $y++) {
                    $springTile = SpringTile::fromXYZ($x, $y, $z);

                    echo "Generating Spring Tile /{$z}/{$x}/{$y}/\n";
                }
            }
        });

        $zoom = collect([0, 5]);

        $zoom->each(function ($z) {
            $tileCount = pow(2, $z);

            for ($x = 0; $x < $tileCount; $x++) {
                for ($y = 0; $y < $tileCount; $y++) {
                    $springTile = WateredSpringTile::fromXYZ($x, $y, $z);

                    echo "Generating Watered Spring Tile /{$z}/{$x}/{$y}/\n";
                }
            }
        });
    }
}
