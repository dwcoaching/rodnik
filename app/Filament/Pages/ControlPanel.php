<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;

class ControlPanel extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.pages.control-panel';

    public static function canAccess(): bool
    {
        return Gate::allows('superadmin');
    }
}
