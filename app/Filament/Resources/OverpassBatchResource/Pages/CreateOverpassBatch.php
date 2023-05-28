<?php

namespace App\Filament\Resources\OverpassBatchResource\Pages;

use Filament\Pages\Actions;
use App\Jobs\CreateOverpassBatchChecks;
use App\Jobs\FetchOverpassBatchImports;
use Filament\Resources\Pages\CreateRecord;
use App\Filament\Resources\OverpassBatchResource;

class CreateOverpassBatch extends CreateRecord
{
    protected static string $resource = OverpassBatchResource::class;

    protected function afterCreate(): void
    {
        $this->record->imports_status = 'not created';
        $this->record->checks_status = 'not created';
        $this->record->fetch_status = 'not started';
        $this->record->parse_status = 'not started';
        $this->record->save();
        $this->record->createImports();

        CreateOverpassBatchChecks::dispatch($this->record);
        FetchOverpassBatchImports::dispatch($this->record);
    }
}
