<?php

declare(strict_types=1);

namespace App\Livewire\Duo\Springs;

use App\Actions\Springs\UnmergeSpringsAction;
use App\Models\Spring;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Reactive;
use Livewire\Component;

final class Show extends Component
{
    #[Reactive]
    public $springId;

    #[Reactive]
    public $userId;

    public function render()
    {
        $spring = Spring::findOrFail($this->springId);

        $reports = $spring
            ->reports()
            ->whereNull('from_osm')
            ->orderByRaw('COALESCE(visited_at, created_at) DESC')
            ->orderByDesc('created_at')
            ->with(['user', 'photos'])
            ->visible()
            ->get();

        $coordinates = [
            (float) ($spring->longitude),
            (float) ($spring->latitude),
        ];

        return view('livewire.duo.springs.show', compact('reports', 'spring', 'coordinates'));
    }

    public function annihilate()
    {
        if (Auth::check() && Auth::user()->is_admin) {
            $spring = Spring::find($this->springId);
            $spring->annihilate();

            return $this->redirect(duo_route(), navigate: true);
        }
        abort(403);

    }

    public function hide()
    {
        if (Auth::check() && Auth::user()->is_admin) {
            $spring = Spring::find($this->springId);
            $spring->hide();

            return $this->redirect(duo_route(), navigate: true);
        }
        abort(403);

    }

    public function invalidateTiles()
    {
        if (! Gate::allows('admin')) {
            abort(403);
        }

        $spring = Spring::findOrFail($this->springId);

        $spring->invalidateTiles();

        return $this->redirect(duo_route(['spring' => $this->springId]), navigate: true);
    }

    public function unmerge(UnmergeSpringsAction $action)
    {
        if (! Gate::allows('admin')) {
            abort(403);
        }

        $spring = Spring::findOrFail($this->springId);
        $action($spring);

        return $this->redirect(duo_route(['spring' => $this->springId]), navigate: true);
    }
}
