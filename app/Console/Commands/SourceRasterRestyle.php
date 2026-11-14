<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\SourceRaster\RasterPyramid;
use Illuminate\Console\Command;
use Throwable;

final class SourceRasterRestyle extends Command
{
    protected $signature = 'sources:raster-restyle {--no-render : Publish fresh URLs and render PNGs on demand}';

    protected $description = 'Repaint the source overview using its existing numeric snapshot';

    public function handle(RasterPyramid $pyramid): int
    {
        try {
            $stats = $pyramid->restyle(! $this->option('no-render'), fn ($stage, $count) => $this->line($stage.': '.$count));
            $this->info('Published restyled source overview '.$stats['revision']);
            $this->line(json_encode($stats, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
