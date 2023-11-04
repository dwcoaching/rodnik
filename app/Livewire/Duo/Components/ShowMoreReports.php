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

            if ($this->skip + $this->take > $user->rating) {
                $this->take = $user->rating - $this->skip;
            }

            if ($this->take < 0) {
                $this->take = 0;
            }
        }

        if ($this->shown) {
            if ($this->userId) {
                $this->reports = $user->reports()
                    ->whereNull('hidden_at')
                    ->with(['user', 'photos', 'spring'])
                    ->latest()
                    ->skip($this->skip)
                    ->take($this->take)
                    ->get();
            } else {
                $this->reports = Report::whereNull('hidden_at')
                    ->whereNull('from_osm')
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
