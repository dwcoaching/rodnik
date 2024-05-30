<?php

namespace App\Livewire\Duo\Springs;

use App\Models\Spring;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;

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
                ->latest()
                ->with(['user', 'photos'])
                ->get();

            $coordinates = [
                floatval($spring->longitude),
                floatval($spring->latitude)
        ];
    }

        return view('livewire.duo.springs.show', compact('reports', 'spring', 'coordinates'));
    }

    public function annihilate()
    {
        if (Auth::check() && Auth::user()->is_superadmin) {
            $spring = Spring::find($this->springId);
            $spring->annihilate();

            $this->redirectRoute('index');
        } else {
            abort(403);
        }
    }
}
