<?php

namespace App\Http\Livewire\Duo\Reports;

use App\Models\Report;
use App\Models\Spring;
use Livewire\Component;
use App\Library\StatisticsService;

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
            $springsCount = null;
            $reportsCount = null;
        } else {
            $lastReports = Report::whereNull('hidden_at')
                ->whereNull('from_osm')
                ->latest()
                ->limit(12)
                ->with(['spring', 'user', 'photos'])
                ->get();

            $springsCount = StatisticsService::getSpringsCount();
            $reportsCount = StatisticsService::getReportsCount();
        }

        return view('livewire.duo.reports.index', compact(
            'lastReports',
            'springsCount',
            'reportsCount',
        ));
    }
}
