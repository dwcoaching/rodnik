<?php

declare(strict_types=1);

namespace App\Filament\Resources\ReportResource\Pages;

use App\Filament\Resources\ReportResource;
use Filament\Resources\Pages\ListRecords;

final class ListReports extends ListRecords
{
    protected static string $resource = ReportResource::class;
}
