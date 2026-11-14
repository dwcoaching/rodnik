<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Services\SourceRaster\RasterPyramid;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Gate;

/** Switches the map's far-zoom overview between the vector layers and the raster tiles. */
final class SourceOverviewWidget extends Widget
{
    protected string $view = 'filament.widgets.source-overview-widget';

    public static function canView(): bool
    {
        return Gate::allows('superadmin');
    }

    public function setEnabled(bool $enabled): void
    {
        Gate::authorize('superadmin');
        $pyramid = app(RasterPyramid::class);

        // Never send visitors to tiles that do not exist.
        if (! $enabled || $pyramid->manifest() !== null) {
            $pyramid->setEnabled($enabled);
        }
    }

    protected function getViewData(): array
    {
        return ['status' => app(RasterPyramid::class)->status()];
    }
}
