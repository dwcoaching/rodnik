<?php

namespace App\Actions\Springs;

use App\Models\Spring;
use App\Rules\SpringTypeRule;
use App\Models\SpringRevision;
use App\Library\StatisticsService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use App\Jobs\SendSpringRevisionNotification;

class UpdateSpring
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

        if ($spring->name != $attributes['name']) {
            $revision->old_name = $spring->name;
            $revision->new_name = $attributes['name'];
            $spring->name = $attributes['name'];
            $springChangeCount++;
        }

        if ($spring->type != $attributes['type']) {
            $revision->old_type = $spring->type;
            $revision->new_type = $attributes['type'];
            $spring->type = $attributes['type'];
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

    public function validate($attributues): void
    {
        Validator::make($attributues, [
            'name' => 'nullable',
            'type' => [new SpringTypeRule],
        ])->validate();
    }
}
