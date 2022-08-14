<?php

namespace App\Console\Commands;

use App\Models\SpringAggregate;
use Illuminate\Console\Command;

class SpringAggregateGlobalCalculate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'aggregate:calculate-global';

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
        $springAggregates = SpringAggregate::whereNull('count')->get();

        foreach ($springAggregates as $springAggregate) {
            $springAggregate->calculate();

            echo "{$springAggregate->id} spring count is {$springAggregate->count}\n";
        }
    }
}
