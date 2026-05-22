<?php

namespace App\Actions\Springs;

use App\Models\Spring;
use App\Library\StatisticsService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class MergeSpringsAction
{
    public function __invoke(Spring $source, $targetId)
    {
        $this->authorize();
        $target = $this->validate($source, $targetId);
        return $this->execute($source, $target);
    }

    public function authorize(): void
    {
        Gate::authorize('admin');
    }

    public function validate(Spring $source, $targetId): Spring
    {
        Validator::make(
            ['redirect_to_spring_id' => $targetId],
            ['redirect_to_spring_id' => ['required', 'integer']],
        )->validate();

        if (! $source->canBeRedirectedFrom()) {
            throw ValidationException::withMessages([
                'redirect_to_spring_id' => 'OSM-tracked water sources cannot be merged. Delete it from OSM first.',
            ]);
        }

        $target = Spring::find($targetId);

        if (! $target || ! $target->canBeRedirectedTo($source)) {
            throw ValidationException::withMessages([
                'redirect_to_spring_id' => 'Target water source does not exist or is not eligible.',
            ]);
        }

        $withinRadius = Spring::query()
            ->where('id', $target->id)
            ->withinMergeRadiusOf($source)
            ->exists();

        if (! $withinRadius) {
            throw ValidationException::withMessages([
                'redirect_to_spring_id' => 'Target water source is farther than ' . Spring::MERGE_RADIUS_METERS . ' meters away.',
            ]);
        }

        return $target;
    }

    public function execute(Spring $source, Spring $target): Spring
    {
        $source->redirect_to_spring_id = $target->id;
        $source->save();

        $source->invalidateTiles();
        StatisticsService::invalidateSpringsCount();

        return $source;
    }
}
