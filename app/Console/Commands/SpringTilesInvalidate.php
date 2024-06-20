<?php

namespace App\Console\Commands;

use App\Models\Spring;
use App\Models\SpringTile;
use Illuminate\Console\Command;
use App\Models\WateredSpringTile;
use App\Library\StatisticsService;

class SpringTilesInvalidate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tiles:invalidate {springId}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate everything related to a Spring';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $spring = Spring::findOrFail(intval($this->argument('springId')));

        SpringTile::invalidate($spring->longitude, $spring->latitude);
        WateredSpringTile::invalidate($spring->longitude, $spring->latitude);
        StatisticsService::invalidateSpringsCount();

        echo 'Done!';
    }
}
