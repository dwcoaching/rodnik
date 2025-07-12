<?php

namespace App\Actions\Springs;

use App\Models\Spring;
use App\Rules\LatitudeRule;
use App\Rules\LongitudeRule;
use App\Rules\SpringTypeRule;
use App\Models\SpringRevision;
use App\Library\StatisticsService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use App\Jobs\SendSpringRevisionNotification;

class PatchSpringsLocationAction
{
    public function __invoke(Spring $spring, $attributes)
    {
        $this->authorize($spring);
        $this->validate($attributes);
        $this->execute($spring, $attributes);
    }

    public function execute(Spring $spring, $attributes)
    {
        $springChangeCount = 0;
        $revision = new SpringRevision();

        if ($spring->latitude != $attributes['latitude']) {
            $revision->old_latitude = $spring->latitude;
            $revision->new_latitude = $attributes['latitude'];
            $spring->latitude = $attributes['latitude'];
            $springChangeCount++;
        }

        if ($spring->longitude != $attributes['longitude']) {
            $revision->old_longitude = $spring->longitude;
            $revision->new_longitude = $attributes['longitude'];
            $spring->longitude = $attributes['longitude'];
            $springChangeCount++;
        }

        if ($springChangeCount) {
            $spring->save();
            $revision->user_id = Auth::check() ? Auth::user()->id : null;
            $revision->spring_id = $spring->id;
            $revision->revision_type = 'user';
            $revision->save();
            StatisticsService::invalidateReportsCount();

            if ($revision->user_id) {
                Auth::user()->updateRating();
            }

            $spring->invalidateTiles();
            StatisticsService::invalidateSpringsCount();

            SendSpringRevisionNotification::dispatch($revision);
        }
    }

    public function authorize($spring): void
    {
        Gate::authorize('update', $spring);
    }

    public function validate($attributes): void
    {
        Validator::make($attributes, [
            'latitude' => [new LatitudeRule],
            'longitude' => [new LongitudeRule],
        ])->validate();
    }
}
