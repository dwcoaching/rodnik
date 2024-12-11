<?php

namespace App\Livewire\Duo\Springs;

use App\Models\Spring;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Reactive;

class Show extends Component
{
    #[Reactive]
    public $springId;

    #[Reactive]
    public $userId;

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
        if (Auth::check() && Auth::user()->is_admin) {
            $spring = Spring::find($this->springId);
            $spring->annihilate();

            $this->redirectRoute('duo');
        } else {
            abort(403);
        }
    }

    public function hide()
    {
        if (Auth::check() && Auth::user()->is_admin) {
            $spring = Spring::find($this->springId);
            $spring->hide();

            return $this->redirectRoute('duo');
        } else {
            abort(403);
        }
    }

    public function invalidateTiles()
    {
        if (! Gate::allows('admin')) {
            abort(403);
        }

        $spring = Spring::findOrFail($this->springId);

        $spring->invalidateTiles();

        return $this->redirectRoute('springs.show', $this->springId);
    }
}
