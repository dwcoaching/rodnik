<?php

namespace App\Livewire\Duo\Springs;

use App\Models\Spring;
use Livewire\Component;
use App\Models\SpringTile;
use App\Rules\LatitudeRule;
use App\Rules\LongitudeRule;
use App\Models\SpringRevision;
use Livewire\Attributes\Locked;
use App\Models\WateredSpringTile;
use Livewire\Attributes\Reactive;
use App\Library\StatisticsService;
use Illuminate\Support\Facades\Auth;
use App\Actions\Springs\PostSpringsAction;
use App\Jobs\SendSpringRevisionNotification;
use App\Actions\Springs\PatchSpringsLocationAction;

class Create extends Component
{
    #[Reactive]
    public $springId;

    #[Reactive]
    public $location = false;

    public $saving;

    public $coordinates;
    public $latitude = null;
    public $longitude = null;

    protected function rules()
    {
        return [
            'latitude' => [new LatitudeRule],
            'longitude' => [new LongitudeRule],
        ];
    }

    public function mount($springId, $location)
    {
        if ($this->springId) {
            $this->spring = Spring::find($this->springId);
            $this->authorize('update', $this->spring);

            $this->coordinates = $this->spring->latitude . ', ' . $this->spring->longitude;
            $this->latitude = $this->spring->latitude;
            $this->longitude = $this->spring->longitude;
        } else {
            $this->authorize('create', Spring::class);
        }
    }

    public function render()
    {
        $this->saving = false;

        return view('livewire.duo.springs.create');
    }

    public function create(PostSpringsAction $postSprings)
    {
        $spring = $postSprings([
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
        ]);

        return redirect()->route('springs.edit', $spring);
    }

    public function update(PatchSpringsLocationAction $patchSpringsLocation)
    {
        $spring = Spring::find($this->springId);

        $patchSpringsLocation($spring, [
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
        ]);

        return redirect()->route('duo', ['s' => $spring->id]);
    }
}
