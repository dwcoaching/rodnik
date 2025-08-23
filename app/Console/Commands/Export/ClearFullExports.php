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
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Builder;

class ClearFullExports extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'export:clear';

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
        $files = Storage::disk('public')->files('exports');
        foreach ($files as $file) {
            Storage::disk('public')->delete($file);
        }
    }
}
