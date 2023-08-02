<?php

namespace App\Http\Livewire\Duo\Components;

use App\Models\Report;
use Livewire\Component;

class ShowMoreReports extends Component
{
    public $shown = false;
    public $skip;
    public $take;
    public $reports = [];

    public function show()
    {
        $this->shown = true;
    }

    public function render()
    {
        if ($this->shown) {
            $this->reports = Report::whereNull('hidden_at')
                ->whereNull('from_osm')
                ->latest()
                ->take($this->take)
                ->skip($this->skip)
                ->with(['spring', 'user', 'photos'])
                ->get();
        }

        return view('livewire.duo.components.show-more-reports');
    }
}
