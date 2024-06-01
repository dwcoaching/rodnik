<?php

namespace App\Livewire\Springs;

use App\Models\Report;
use App\Models\Spring;
use Livewire\Component;
use App\Models\SpringTile;
use App\Rules\LatitudeRule;
use App\Rules\LongitudeRule;
use App\Rules\SpringTypeRule;
use App\Models\SpringRevision;
use Illuminate\Validation\Rule;
use App\Models\WateredSpringTile;
use App\Library\StatisticsService;
use App\Jobs\SendReportNotification;
use Illuminate\Support\Facades\Auth;
use App\Jobs\SendSpringRevisionNotification;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class Create extends Component
{
    use AuthorizesRequests;

    public $springId;
    public $name;
    public $type;
    public $coordinates;
    public $latitude;
    public $longitude;

    protected $spring;

    protected function rules()
    {
        return [
            'name' => 'nullable',
            'type' => [new SpringTypeRule],
            'latitude' => [new LatitudeRule],
            'longitude' => [new LongitudeRule],
        ];
    }

    public function mount($springId)
    {
        $this->springId = $springId;

        if ($this->springId) {
            $this->spring = Spring::find($this->springId);

            $this->authorize('update', $this->spring);

            $this->coordinates = $this->spring->latitude . ', ' . $this->spring->longitude;
            $this->type = $this->spring->type;
            $this->name = $this->spring->name;
            $this->latitude = $this->spring->latitude;
            $this->longitude = $this->spring->longitude;
        } else {
            $this->authorize('create', Spring::class);
        }
    }

    public function render()
    {
        return view('livewire.springs.create', [
            'waterSourceTypes' => Spring::TYPES
        ]);
    }

    public function store()
    {
        $this->validate();

        if ($this->springId) {
            $this->spring = Spring::find($this->springId);
        } else {
            $this->spring = new Spring();
        }

        $springChangeCount = 0;
        $revision = new SpringRevision();

        if ($this->spring->latitude != $this->latitude
            || $this->spring->longitude != $this->longitude) {
            SpringTile::invalidate($this->spring->longitude, $this->spring->latitude);
            WateredSpringTile::invalidate($this->spring->longitude, $this->spring->latitude);
            SpringTile::invalidate($this->longitude, $this->latitude);
            WateredSpringTile::invalidate($this->longitude, $this->latitude);
        }

        if ($this->spring->latitude != $this->latitude) {
            $revision->old_latitude = $this->spring->latitude;
            $revision->new_latitude = $this->latitude;
            $this->spring->latitude = $this->latitude;
            $springChangeCount++;
        }

        if ($this->spring->longitude != $this->longitude) {
            $revision->old_longitude = $this->spring->longitude;
            $revision->new_longitude = $this->longitude;
            $this->spring->longitude = $this->longitude;
            $springChangeCount++;
        }

        if ($this->spring->name != $this->name) {
            $revision->old_name = $this->spring->name;
            $revision->new_name = $this->name;
            $this->spring->name = $this->name;
            $springChangeCount++;
        }

        if ($this->spring->type != $this->type) {
            $revision->old_type = $this->spring->type;
            $revision->new_type = $this->type;
            $this->spring->type = $this->type;
            $springChangeCount++;
        }

        if ($springChangeCount) {
            if ($this->spring->id) {
                $this->authorize('update', $this->spring);
            } else {
                $this->authorize('create', Spring::class);
            }

            $this->spring->save();
            $revision->user_id = Auth::check() ? Auth::user()->id : null;
            $revision->spring_id = $this->spring->id;
            $revision->revision_type = 'user';
            $revision->save();
            StatisticsService::invalidateReportsCount();

            if ($revision->user_id) {
                Auth::user()->updateRating();
            }

            $this->spring->invalidateTiles();
            StatisticsService::invalidateSpringsCount();

            SendSpringRevisionNotification::dispatch($revision);
        }

        return $this->redirect(route('springs.show', $this->spring));
    }
}
