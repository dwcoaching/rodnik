<?php

namespace App\Console\Commands\Export;

use App\Models\User;
use App\Models\Spring;
use Illuminate\Console\Command;
use App\Library\Export\Selector;
use App\Library\Export\CsvWriter;
use App\Library\Export\JsonExport;
use App\Library\Export\JsonWriter;
use App\Library\Export\XlsxWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Builder;

class FullExport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'export:full';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle(Selector $selector)
    {

        Artisan::call('export:clear');        
        Artisan::call('export:springs', ['--format' => 'json']);
        Artisan::call('export:springs', ['--format' => 'csv']);
        Artisan::call('export:springs', ['--format' => 'xlsx']);
    }
}
