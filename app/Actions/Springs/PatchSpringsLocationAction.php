<?php

declare(strict_types=1);

namespace App\Actions\Springs;

use App\Jobs\SendSpringRevisionNotification;
use App\Library\StatisticsService;
use App\Models\Spring;
use App\Models\SpringRevision;
use App\Models\SpringTile;
use App\Models\WateredSpringTile;
use App\Rules\LatitudeRule;
use App\Rules\LongitudeRule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

final class PatchSpringsLocationAction
{
    public function __invoke(Spring $spring, $attributes)
    {
        $this->authorize($spring);
        $this->validate($attributes);
        $this->execute($spring, $attributes);
    }

    public function execute(Spring $spring, $attributes)
    {
        $oldLongitude = $spring->longitude;
        $oldLatitude = $spring->latitude;
        $springChangeCount = 0;
        $revision = new SpringRevision();

        if ((float) $spring->latitude !== (float) $attributes['latitude']) {
            $revision->old_latitude = $spring->latitude;
            $revision->new_latitude = $attributes['latitude'];
            $spring->latitude = $attributes['latitude'];
            $springChangeCount++;
        }

        if ((float) $spring->longitude !== (float) $attributes['longitude']) {
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

            SpringTile::invalidate($oldLongitude, $oldLatitude);
            WateredSpringTile::invalidate($oldLongitude, $oldLatitude);
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
