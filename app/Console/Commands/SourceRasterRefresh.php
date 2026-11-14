<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\SourceRaster\RasterPyramid;
use Illuminate\Console\Command;
use Throwable;

final class SourceRasterRefresh extends Command
{
    protected $signature = 'sources:raster-refresh';

    protected $description = 'Refresh the source overview tiles affected by source or report changes';

    public function handle(RasterPyramid $pyramid): int
    {
        try {
            $this->info('Source overview refresh: '.json_encode($pyramid->refresh(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
