<?php

namespace App\Http\Livewire\Duo\Springs;

use App\Models\Spring;
use Livewire\Component;

class Show extends Component
{
    public $springId;

    public function setSpring($springId)
    {
        $this->springId = $springId;
    }

    public function render()
    {
        if (! $this->springId) {
            $spring = null;
            $reports = [];
            $coordinates = [];
        } else {
            if (! $spring = Spring::find($this->springId)) {
                abort(404);
            }

            $reports = $spring
                ->reports()
                ->whereNull('from_osm')
                ->orderByDesc('visited_at')
                ->with(['user', 'photos'])
                ->get();

            $coordinates = [
                floatval($spring->longitude),
                floatval($spring->latitude)
        ];
    }

        return view('livewire.duo.springs.show', compact('reports', 'spring', 'coordinates'));
    }
}
