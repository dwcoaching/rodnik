<?php

namespace App\Filament\Resources\SpringResource\Pages;

use App\Filament\Resources\SpringResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSpring extends EditRecord
{
    protected static string $resource = SpringResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
