<?php

namespace App\Console\Commands;

use App\Models\SpringTile;
use Illuminate\Console\Command;

class SpringTilesGlobalInvalidate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tiles:invalidate-global';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove all generated files and updates DB';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        SpringTile::whereNotNull('generated_at')
            ->each(function ($item) {
                $item->deleteFile();
                echo 'SpringTile ' . $item->id . ' invalidated' . "\n";
            });
    }
}
