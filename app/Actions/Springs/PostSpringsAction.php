<?php

declare(strict_types=1);

namespace App\Actions\Springs;

use App\Jobs\SendSpringRevisionNotification;
use App\Library\StatisticsService;
use App\Models\Spring;
use App\Models\SpringRevision;
use App\Models\SpringTile;
use App\Models\User;
use App\Models\WateredSpringTile;
use App\Rules\LatitudeRule;
use App\Rules\LongitudeRule;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class PostSpringsAction
{
    /**
     * @param  array<string, mixed>  $attributes  latitude and longitude, optionally name and type
     */
    public function __invoke(User $user, array $attributes): Spring
    {
        $this->authorize($user);

        return $this->execute($user, $this->validate($attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(User $user, array $attributes): Spring
    {
        $spring = new Spring();
        $revision = new SpringRevision();

        $revision->old_latitude = null;
        $revision->new_latitude = $attributes['latitude'];
        $spring->latitude = $attributes['latitude'];

        $revision->old_longitude = null;
        $revision->new_longitude = $attributes['longitude'];
        $spring->longitude = $attributes['longitude'];

        if (($attributes['name'] ?? null) !== null) {
            $revision->new_name = $attributes['name'];
            $spring->name = $attributes['name'];
        }

        if (($attributes['type'] ?? null) !== null) {
            $revision->new_type = $attributes['type'];
            $spring->type = $attributes['type'];
        }

        $spring->save();
        $revision->user_id = $user->id;
        $revision->spring_id = $spring->id;
        $revision->revision_type = 'user';
        $revision->save();

        SpringTile::invalidate($spring->longitude, $spring->latitude);
        WateredSpringTile::invalidate($spring->longitude, $spring->latitude);

        StatisticsService::invalidateReportsCount();
        StatisticsService::invalidateSpringsCount();

        $user->updateRating();

        SendSpringRevisionNotification::dispatch($revision);

        return $spring;
    }

    public function authorize(User $user): void
    {
        Gate::forUser($user)->authorize('create', Spring::class);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function validate(array $attributes): array
    {
        return Validator::make($attributes, [
            'latitude' => ['required', 'numeric', new LatitudeRule],
            'longitude' => ['required', 'numeric', new LongitudeRule],
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'type' => ['sometimes', 'nullable', 'string', Rule::in(Spring::TYPES)],
        ], [
            'latitude.required' => __('ui.validation.invalid_coordinates'),
            'latitude.numeric' => __('ui.validation.invalid_coordinates'),
            'longitude.required' => __('ui.validation.invalid_coordinates'),
            'longitude.numeric' => __('ui.validation.invalid_coordinates'),
            'type.in' => __('ui.validation.select_water_source_type'),
        ])->validate();
    }
}
