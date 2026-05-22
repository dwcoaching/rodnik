<?php

namespace App\Livewire\Reports;

use App\Models\Report;
use Livewire\Component;
use App\Library\StatisticsService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use App\Actions\Reports\PostReportsBanAction;
use App\Actions\Reports\DeleteReportsBanAction;
use App\Actions\Reports\MoveReportToMergeTargetAction;
use App\Actions\Reports\MoveReportBackToRedirectedSourceAction;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class Show extends Component
{
    use AuthorizesRequests;

    public $report;
    public $justHidden = false;
    public $justMoved = false;
    public ?int $movedFromSpringId = null;
    public ?int $movedToSpringId = null;

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

    public function moveToRedirectTarget(MoveReportToMergeTargetAction $moveReportToMergeTarget)
    {
        if (! Gate::allows('admin')) {
            abort(403);
        }

        $this->report->load('spring');
        $target = $this->report->spring->visibleMergeTargetForReports();

        if (! $target) {
            abort(404);
        }

        $this->movedFromSpringId = (int) $this->report->spring_id;
        $this->movedToSpringId = (int) $target->id;
        $this->report = $moveReportToMergeTarget($this->report);
        $this->justMoved = true;
    }

    public function undoMoveToRedirectTarget(MoveReportBackToRedirectedSourceAction $moveReportBackToRedirectedSource)
    {
        if (! Gate::allows('admin')) {
            abort(403);
        }

        if (! $this->justMoved || ! $this->movedFromSpringId) {
            return;
        }

        $this->report = $moveReportBackToRedirectedSource($this->report, $this->movedFromSpringId);
        $this->justMoved = false;
        $this->movedFromSpringId = null;
        $this->movedToSpringId = null;
    }

    public function render()
    {
        return view('livewire.reports.show');
    }
}
