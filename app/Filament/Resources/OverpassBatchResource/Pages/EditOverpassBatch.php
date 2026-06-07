<?php

namespace App\Filament\Resources\OverpassBatchResource\Pages;

use App\Filament\Resources\OverpassBatchResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditOverpassBatch extends EditRecord
{
    protected static string $resource = OverpassBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->before(function(DeleteAction $action) {
                $this->record->deleteArtifacts();
            }),
        ];
    }
}
