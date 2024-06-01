<?php

namespace App\Livewire\Duo\Components;

use App\Models\User;
use App\Models\Report;
use Livewire\Component;

class ShowMoreReports extends Component
{
    public $shown = false;
    public $skip;
    public $take;
    public $reports = [];
    public $userId = null;

    public function show()
    {
        $this->shown = true;
    }

    public function render()
    {
        if ($this->userId) {
            $user = User::find($this->userId);
            $userReportCount = $user->calculateRating(); // That will be unnecessary if we ensure that the cached rating is always updated

            if ($this->skip + $this->take > $userReportCount) {
                $this->take = $userReportCount - $this->skip;
            }

            if ($this->take < 0) {
                $this->take = 0;
            }
        }

        if ($this->shown) {
            if ($this->userId) {
                $this->reports = $user->reports()
                    ->select('reports.*')
                    ->join('springs', 'springs.id', '=', 'reports.spring_id')
                    ->whereNull('reports.hidden_at')
                    ->whereNull('reports.from_osm')
                    ->whereNull('springs.hidden_at')
                    ->with(['user', 'photos', 'spring'])
                    ->latest()
                    ->skip($this->skip)
                    ->take($this->take)
                    ->get();
            } else {
                $this->reports = Report::select('reports.*')
                    ->join('springs', 'springs.id', '=', 'reports.spring_id')
                    ->whereNull('reports.hidden_at')
                    ->whereNull('reports.from_osm')
                    ->whereNull('springs.hidden_at')
                    ->latest()
                    ->take($this->take)
                    ->skip($this->skip)
                    ->with(['spring', 'user', 'photos'])
                    ->get();
            }
        }

        return view('livewire.duo.components.show-more-reports');
    }
}
