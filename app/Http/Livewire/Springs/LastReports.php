<?php

namespace App\Http\Livewire\Springs;

use App\Models\Report;
use Livewire\Component;

class LastReports extends Component
{
    public $userId;

    public function render()
    {
        $user = null;

        if ($this->userId) {
            $user = User::find($this->userId);
            $lastReports = $user->reports()
                ->whereNull('hidden_at')
                ->latest()
                ->limit(10)
                ->get();
        } else {
            $user = null;
            $lastReports = Report::whereNull('hidden_at')->latest()->limit(10)->get();
        }

        return view('livewire.springs.last-reports', compact('lastReports', 'user'));
    }
}
