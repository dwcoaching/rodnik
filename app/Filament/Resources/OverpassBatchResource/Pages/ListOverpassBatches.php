<?php

namespace App\Filament\Resources\OverpassBatchResource\Pages;

use App\Filament\Resources\OverpassBatchResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOverpassBatches extends ListRecords
{
    protected static string $resource = OverpassBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
