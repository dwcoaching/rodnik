<?php

namespace App\Livewire\Duo\Springs;

use Livewire\Attributes\Locked;
use App\Models\Spring;
use Livewire\Component;
use App\Models\SpringTile;
use App\Rules\LatitudeRule;
use App\Rules\LongitudeRule;
use App\Models\SpringRevision;
use App\Models\WateredSpringTile;
use App\Library\StatisticsService;
use Illuminate\Support\Facades\Auth;
use App\Jobs\SendSpringRevisionNotification;

class Create extends Component
{
    #[Locked]
    public $springId;

    public $coordinates;
    public $latitude;
    public $longitude;
    public $locationMode = false;

    protected function rules()
    {
        return [
            'latitude' => [new LatitudeRule],
            'longitude' => [new LongitudeRule],
        ];
    }

    public function mount($springId, $locationMode)
    {
        $this->springId = $springId;
        $this->locationMode = $locationMode;

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

    public function setSpring($springId)
    {
        $this->springId = $springId;
    }

    public function render()
    {
        return view('livewire.duo.springs.create');
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
