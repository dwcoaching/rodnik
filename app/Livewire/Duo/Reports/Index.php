<?php

namespace App\Livewire\Duo\Reports;

use App\Models\Report;
use App\Models\Spring;
use Livewire\Component;
use App\Library\StatisticsService;
use Illuminate\Database\Eloquent\Builder;

class Index extends Component
{
    public $loaded;

    public $limit = 12;

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
            $lastReports = Report::select('reports.*')
                ->join('springs', 'springs.id', '=', 'reports.spring_id')
                ->whereNull('reports.hidden_at')
                ->whereNull('reports.from_osm')
                ->whereNull('springs.hidden_at')
                ->latest('reports.created_at')
                ->limit($this->limit)
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
