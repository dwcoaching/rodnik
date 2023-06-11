<?php

namespace App\Http\Livewire\Duo\Reports;

use App\Models\Report;
use Livewire\Component;

class Index extends Component
{
    public $loaded;

    public function setLoaded()
    {
        $this->loaded = true;
    }

    public function render()
    {
        if (! $this->loaded) {
            $lastReports = [];
        } else {
            $lastReports = Report::whereNull('hidden_at')
                ->whereNull('from_osm')
                ->latest()
                ->limit(12)
                ->with(['spring', 'user', 'photos'])
                ->get();
        }

        return view('livewire.duo.reports.index', compact('lastReports'));
    }
}
