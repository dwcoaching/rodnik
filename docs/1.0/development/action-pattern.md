- [Action Pattern](#action-pattern)
- [Naming](#naming)
- [Structure](#file-structure)
- [Testing Actions](#testing-actions)
- [Full Example](#full-example)

<a name="action-pattern"></a>
## Action Pattern
All Livewire component writes should be done through Actions

<a name="naming"></a>
## Naming
Actions should be descriptive and have `Action.php` at the end.

There was an idea of strictly following REST principles and having
names like `PostReportsBanAction`, `DeleteReportsBanAction` instead
of `HideReportByModeratorAction` and `UnhideReportByModeratorAction`.

All Actions should be in the app/Actions folder without further subfolders.

<a name="structure"></a>
## Structure
All Actions should be invokable classes with this structure:
```
    public function __invoke(array $data)
    {
        $this->data = $data;
        
        $this->authorize();
        $this->validate();
        return $this->execute();
    }
```

Validate and authorize methods should use classic Laravel approach,
it will be caught by Livewire to get the end user the proper feedback.

### Authorization rules
- Use Policies or Gates where appropriate

<a name="testing-actions"></a>
## Testing Actions
- Generate a test for each Action
- Test validation (both pass and fail)
- Test authorization (both pass and fail)
- Test that database changes actually happen

<a name="full-example"></a>
## Full Example
Livewire component usage:
```
    public function update(PatchSpringsLocationAction $patchSpringsLocation)
    {
        $spring = Spring::find($this->springId);

        $patchSpringsLocation($spring, [
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
        ]);

        return redirect()->route('duo', ['s' => $spring->id]);
    }
```

Action class:
```
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

    public function validate($attributues): void
    {
        Validator::make($attributues, [
            'latitude' => [new LatitudeRule],
            'longitude' => [new LongitudeRule],
        ])->validate();
    }
}