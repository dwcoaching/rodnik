<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\SourceRaster\RasterPyramid;
use Illuminate\Console\Command;
use Throwable;

final class SourceRasterBuild extends Command
{
    protected $signature = 'sources:raster-build {--max-zoom=10 : Last overview zoom (0–10)} {--no-render : Publish numeric data and render PNGs on demand}';

    protected $description = 'Build a complete, immutable source overview and report actual generation costs';

    public function handle(RasterPyramid $pyramid): int
    {
        if (! ctype_digit((string) $this->option('max-zoom')) || (int) $this->option('max-zoom') > 10) {
            $this->error('Maximum zoom must be an integer between 0 and 10.');

            return self::FAILURE;
        }
        try {
            $stats = $pyramid->build((int) $this->option('max-zoom'), ! $this->option('no-render'), fn ($stage, $count) => $this->line($stage.': '.$count));
            $this->info('Published source overview '.$stats['revision']);
            $this->line(json_encode($stats, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
