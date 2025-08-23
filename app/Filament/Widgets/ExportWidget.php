<?php

namespace App\Filament\Widgets;

use App\Library\Tagger;
use App\Library\Laundry;
use App\Jobs\CreateExports;
use Filament\Widgets\Widget;

class ExportWidget extends Widget
{
    public $started = false;

    protected static string $view = 'filament.widgets.export-widget';

    public $tags = [];

    public function mount() {
        
    }

    public function start()
    {
        $this->started = true;
        CreateExports::dispatch();
    }
}
