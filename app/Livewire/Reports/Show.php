<?php

namespace App\Livewire\Reports;

use App\Models\Report;
use Livewire\Component;
use App\Library\StatisticsService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class Show extends Component
{
    use AuthorizesRequests;

    public $report;
    public $hasName;
    public $justHidden = false;

    public function mount(Report $report, $hasName)
    {
        $this->report = $report;
        $this->hasName = $hasName == 'true' ? true : false;
    }

    public function hideByAuthor()
    {
        $this->authorize('update', $this->report);

        $this->report->hidden_at = now();
        $this->report->hidden_by_author_id = Auth::user()->id;
        $this->report->save();
        $this->justHidden = true;

        $this->report->spring->invalidateTiles();
        Auth::user()->updateRating();
        StatisticsService::invalidateReportsCount();
        $this->report->fresh();
    }

    // public function hideByModerator()
    // {
    //     if (Auth::user()->is_moderator) {
    //         $this->report->hidden_at = now();
    //         $this->report->hidden_by_moderator_id = Auth::user()->id;
    //         $this->report->save();
    //         $this->justHidden = true;

    //         $this->report->spring->invalidateTiles();
    //     }
    // }

    public function unhideByAuthor()
    {
        $this->authorize('update', $this->report);

        $this->report->hidden_at = null;
        $this->report->hidden_by_author_id = null;
        $this->report->save();
        $this->justHidden = false;

        $this->report->spring->invalidateTiles();
        Auth::user()->updateRating();
        StatisticsService::invalidateReportsCount();
        $this->report->refresh();
    }

    public function render()
    {
        return view('livewire.reports.show');
    }
}
