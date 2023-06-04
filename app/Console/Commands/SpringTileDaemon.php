<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class SpringTileDaemon extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tiles:daemon';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start a daemon that is looking for missing
        spring tiles and generates them';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        while (true) {
            Artisan::call('tiles:generate-global');

            sleep(60);
        }
    }
}
