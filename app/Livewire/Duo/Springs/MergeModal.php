<?php

namespace App\Livewire\Duo\Springs;

use App\Models\Spring;
use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use App\Actions\Springs\MergeSpringsAction;

class MergeModal extends Component
{
    public ?int $springId = null;
    public ?int $targetSpringId = null;
    public bool $open = false;

    #[On('open-merge-modal')]
    public function openModal($springId)
    {
        if (! Gate::allows('admin')) {
            abort(403);
        }

        $this->resetErrorBag();
        $this->springId = (int) $springId;
        $this->targetSpringId = null;
        $this->open = true;
    }

    public function close()
    {
        $this->open = false;
        $this->springId = null;
        $this->targetSpringId = null;
        $this->resetErrorBag();
    }

    public function merge(MergeSpringsAction $action)
    {
        if (! Gate::allows('admin')) {
            abort(403);
        }

        $source = Spring::findOrFail($this->springId);

        try {
            $action($source, $this->targetSpringId);
        } catch (ValidationException $e) {
            throw $e;
        }

        $this->close();

        return $this->redirect(duo_route(['spring' => $source->id]) . '&redirect=false');
    }

    public function render()
    {
        $source = $this->springId ? Spring::find($this->springId) : null;

        $candidates = collect();

        if ($source && $this->open) {
            $candidates = Spring::query()
                ->where('id', '!=', $source->id)
                ->whereNull('hidden_at')
                ->notRedirected()
                ->withinMergeRadiusOf($source)
                ->orderBy('id')
                ->get()
                ->filter(fn (Spring $candidate) => $candidate->canBeRedirectedTo($source))
                ->values();
        }

        return view('livewire.duo.springs.merge-modal', [
            'source' => $source,
            'candidates' => $candidates,
            'radiusMeters' => Spring::MERGE_RADIUS_METERS,
        ]);
    }
}
