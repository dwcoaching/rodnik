<?php

namespace App\Filament\Resources\OverpassBatchResource\Pages;

use App\Filament\Resources\OverpassBatchResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOverpassBatches extends ListRecords
{
    protected static string $resource = OverpassBatchResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
