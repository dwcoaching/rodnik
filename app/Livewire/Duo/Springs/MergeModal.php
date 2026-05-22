<?php

namespace App\Livewire\Duo\Springs;

use App\Models\Spring;
use Livewire\Component;
use Livewire\Attributes\On;
use App\Library\HaversineDistance;
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
        $candidateDistanceLabels = collect();

        if ($source && $this->open) {
            $distance = app(HaversineDistance::class);
            $distanceLabels = [];
            $distanceMetersById = [];

            $candidates = Spring::query()
                ->where('id', '!=', $source->id)
                ->whereNull('hidden_at')
                ->notRedirected()
                ->withinMergeRadiusOf($source)
                ->orderBy('id')
                ->get()
                ->filter(function (Spring $candidate) use ($source, $distance, &$distanceLabels, &$distanceMetersById) {
                    if (! $candidate->canBeRedirectedTo($source)) {
                        return false;
                    }

                    $distanceMeters = $distance->metersBetweenSprings($source, $candidate);

                    if ($distanceMeters === null || $distanceMeters > Spring::MERGE_RADIUS_METERS) {
                        return false;
                    }

                    $distanceMetersById[$candidate->id] = $distanceMeters;
                    $distanceLabels[$candidate->id] = $distance->formatMeters($distanceMeters);

                    return true;
                })
                ->sortBy(fn (Spring $candidate) => $distanceMetersById[$candidate->id])
                ->values();

            $candidateDistanceLabels = collect($distanceLabels);
        }

        return view('livewire.duo.springs.merge-modal', [
            'source' => $source,
            'candidates' => $candidates,
            'candidateDistanceLabels' => $candidateDistanceLabels,
            'radiusMeters' => Spring::MERGE_RADIUS_METERS,
        ]);
    }
}
