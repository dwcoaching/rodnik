<?php

declare(strict_types=1);

namespace App\Console\Commands\Export;

use App\Library\Export\CsvWriter;
use App\Library\Export\JsonWriter;
use App\Library\Export\Selector;
use App\Library\Export\XlsxWriter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

final class FullExport extends Command
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
    protected $description = 'Export all public springs data and remove the previous full exports';

    /**
     * Execute the console command.
     */
    public function handle(Selector $selector)
    {
        $filenames = [
            (new JsonWriter($selector->forUser(null)->getQuery()))->save(),
            (new CsvWriter($selector->forUser(null)->getQuery()))->save(),
            (new XlsxWriter($selector->forUser(null)->getQuery()))->save(),
        ];

        // Previous exports are removed only once all new ones are in place,
        // so the exports page and the latest links always have a file to serve.
        collect(Storage::disk('public')->files('exports'))
            ->reject(fn (string $file) => in_array(basename($file), $filenames, true))
            ->each(fn (string $file) => Storage::disk('public')->delete($file));
    }
}
