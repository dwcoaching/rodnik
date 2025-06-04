<?php

namespace App\Actions\Springs;

use App\Models\Spring;
use App\Models\SpringTile;
use App\Rules\LatitudeRule;
use App\Rules\LongitudeRule;
use App\Rules\SpringTypeRule;
use App\Models\SpringRevision;
use App\Models\WateredSpringTile;
use App\Library\StatisticsService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use App\Jobs\SendSpringRevisionNotification;

class PostSpringsAction
{
    public function __invoke($attributes)
    {
        $this->authorize();
        $this->validate($attributes);

        return $this->execute($attributes);
    }

    public function execute($attributes): Spring
    {
        $spring = new Spring();
        $revision = new SpringRevision();

        $revision->old_latitude = null;
        $revision->new_latitude = $attributes['latitude'];
        $spring->latitude = $attributes['latitude'];

        $revision->old_longitude = null;
        $revision->new_longitude = $attributes['longitude'];
        $spring->longitude = $attributes['longitude'];

        $spring->save();
        $revision->user_id = Auth::user()->id;
        $revision->spring_id = $spring->id;
        $revision->revision_type = 'user';
        $revision->save();

        SpringTile::invalidate($spring->longitude, $spring->latitude);
        WateredSpringTile::invalidate($spring->longitude, $spring->latitude);

        StatisticsService::invalidateReportsCount();
        StatisticsService::invalidateSpringsCount();

        Auth::user()->updateRating();

        SendSpringRevisionNotification::dispatch($revision);

        return $spring;
    }

    public function authorize(): void
    {
        Gate::authorize('create', Spring::class);
    }

    public function validate($attributes): void
    {
        Validator::make($attributes, [
            'latitude' => [new LatitudeRule],
            'longitude' => [new LongitudeRule],
        ])->validate();
    }
}
