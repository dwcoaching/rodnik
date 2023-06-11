<?php

namespace App\Http\Livewire\Reports;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Models\Report;

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
        $this->report->refresh();
    }

    public function render()
    {
        return view('livewire.reports.show');
    }
}
