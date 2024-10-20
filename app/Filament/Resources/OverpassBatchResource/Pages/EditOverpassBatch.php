<?php

namespace App\Filament\Resources\OverpassBatchResource\Pages;

use Filament\Pages\Actions;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use App\Filament\Resources\OverpassBatchResource;

class EditOverpassBatch extends EditRecord
{
    protected static string $resource = OverpassBatchResource::class;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make()->before(function(DeleteAction $action) {
                $this->record->deleteArtifacts();
            }),
        ];
    }
}
