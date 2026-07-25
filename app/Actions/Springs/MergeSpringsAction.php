<?php

declare(strict_types=1);

namespace App\Actions\Springs;

use App\Library\HaversineDistance;
use App\Library\StatisticsService;
use App\Models\Spring;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class MergeSpringsAction
{
    public function __construct(
        private HaversineDistance $distance,
    ) {}

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
                'redirect_to_spring_id' => __('ui.actions.merge_osm_source'),
            ]);
        }

        $target = Spring::find($targetId);

        if (! $target || ! $target->canBeRedirectedTo($source)) {
            throw ValidationException::withMessages([
                'redirect_to_spring_id' => __('ui.actions.merge_target_ineligible'),
            ]);
        }

        $distanceMeters = $this->distance->metersBetweenSprings($source, $target);

        if ($distanceMeters === null || $distanceMeters > Spring::MERGE_RADIUS_METERS) {
            throw ValidationException::withMessages([
                'redirect_to_spring_id' => __('ui.actions.merge_target_too_far', ['distance' => Spring::MERGE_RADIUS_METERS]),
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
