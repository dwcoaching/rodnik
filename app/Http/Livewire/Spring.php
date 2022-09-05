<?php

namespace App\Http\Livewire;

use App\Models\User;
use App\Models\Report;
use Livewire\Component;
use App\Models\Spring as SpringModel;

class Spring extends Component
{
    public $springId;
    public $userId;

    public function setSpring($springId)
    {
        $this->springId = $springId;
    }

    public function unselectSpring()
    {
        $this->springId = null;
    }

    public function render()
    {
        $user = null;

        if ($this->springId) {
            if (! $spring = SpringModel::find($this->springId)) {
                abort(404);
            }

            $reports = $spring->reports()->orderByDesc('created_at')->get();
            $coordinates = [
                floatval($spring->longitude),
                floatval($spring->latitude)
            ];

            $lastReports = [];
        } else
        {
            $spring = null;
            $reports = [];
            $coordinates = [];

            if ($this->userId) {
                $user = User::find($this->userId);
                $lastReports = $user->reports()->latest()->limit(10)->get();
            } else {
                $user = null;
                $lastReports = Report::latest()->limit(10)->get();
            }

        }

        return view('livewire.spring', compact('reports', 'spring', 'coordinates', 'lastReports', 'user'));
    }
}
