<?php

namespace App\Http\Livewire\Reports;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;

class Show extends Component
{
    public $report;
    public $hasName;

    public function mount($report, $hasName)
    {
        $this->report = $report;
        $this->hasName = $hasName == 'true' ? true : false;
    }

    public function hideByAuthor()
    {
        if (Auth::user()->id == $this->report->user_id) {
            $this->report->hidden_at = now();
            $this->report->hidden_by_author_id = Auth::user()->id;
            $this->report->save();
        }
    }

    public function hideByModerator()
    {
        if (Auth::user()->is_moderator) {
            $this->report->hidden_at = now();
            $this->report->hidden_by_moderator_id = Auth::user()->id;
            $this->report->save();
        }
    }

    public function render()
    {
        return view('livewire.reports.show');
    }
}
