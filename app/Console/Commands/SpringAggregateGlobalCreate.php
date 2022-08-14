<?php

namespace App\Console\Commands;

use App\Models\SpringAggregate;
use Illuminate\Console\Command;

class SpringAggregateGlobalCreate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'aggregate:create-global';

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
        $step = 1;
        $latitudeStart = -90;
        $longitude = -180;

        for ($latitude = -90; $latitude <= 90 - $step; $latitude = $latitude + $step) {
            for ($longitude = -180; $longitude <= 180 - $step; $longitude = $longitude + $step) {
                $springAggreate = new SpringAggregate();
                $springAggreate->latitude = $latitude + $step / 2;
                $springAggreate->longitude = $longitude + $step / 2;
                $springAggreate->step = $step;
                $springAggreate->save();
            }
        }
    }
}
