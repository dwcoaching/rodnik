<?php

namespace App\Livewire\Reports;

use App\Models\Report;
use Livewire\Component;
use App\Library\StatisticsService;
use Illuminate\Support\Facades\Auth;
use App\Actions\Reports\PostReportsBanAction;
use App\Actions\Reports\DeleteReportsBanAction;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class Show extends Component
{
    use AuthorizesRequests;

    public $report;
    public $justHidden = false;

    public function mount(Report $report)
    {
        $this->report = $report;
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
        $this->report->refresh();
    }

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

    public function hideByModerator(PostReportsBanAction $postReportsBan)
    {
        $postReportsBan($this->report);

        $this->report->refresh();
        $this->justHidden = true;
    }

    public function unhideByModerator(DeleteReportsBanAction $deleteReportsBan)
    {
        $deleteReportsBan($this->report);

        $this->report->refresh();
        $this->justHidden = false;
    }

    public function render()
    {
        return view('livewire.reports.show');
    }
}
