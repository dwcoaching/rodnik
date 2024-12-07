<?php

namespace App\Livewire\Duo\Reports;

use App\Models\User;
use App\Models\Report;
use App\Models\Spring;
use Livewire\Component;
use Livewire\Attributes\Reactive;
use App\Library\StatisticsService;
use Illuminate\Database\Eloquent\Builder;

class Index extends Component
{
    #[Reactive]
    public $userId;

    public $limit = 12;

    public function render()
    {
        $springsCount = null;
        $reportsCount = null;
        $user = null;

        if ($this->userId) {
            if (! $user = User::find($this->userId)) {
                abort(404);
            }

            $lastReports = $user->reports()
                ->select('reports.*')
                ->with(['photos', 'user', 'spring'])
                ->whereNull('reports.hidden_at')
                ->join('springs', 'springs.id', '=', 'reports.spring_id')
                ->whereNull('springs.hidden_at')
                ->latest('reports.created_at')
                ->limit($this->limit)
                ->get();
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
            'user',
            'lastReports',
            'springsCount',
            'reportsCount',
        ));
    }
}
