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

class SpringsExport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'export:springs {--userId=} {--format=json}';

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
        $userId = $this->option('userId');
        $format = $this->option('format');

        $user = User::find($userId);

        $query = $selector->forUser($user)->getQuery();

        match ($format) {
            'json' => (new JsonWriter($query))->forUser($user)->save(),
            'csv' => (new CsvWriter($query))->forUser($user)->save(),
            'xlsx' => (new XlsxWriter($query))->forUser($user)->save(),
        };
    }
}
