<?php

namespace App\Filament\Widgets;

use App\Library\Tagger;
use App\Library\Laundry;
use Filament\Widgets\Widget;
use App\Jobs\CleanupOSMSprings;

class CleanupWidget extends Widget
{
    public $started = false;

    protected static string $view = 'filament.widgets.cleanup-widget';

    public $tags = [];

    public function mount() {
        $tagsArray = collect(Laundry::getFalsePositiveTags());

        $this->tags = $tagsArray->map(function ($tagsCombination) {
            return Tagger::parseTags($tagsCombination);
        });
    }

    public function start()
    {
        $this->started = true;
        CleanupOSMSprings::dispatch();
    }
}
