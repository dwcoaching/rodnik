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
use App\Actions\Springs\PatchSpringsAction;
use App\Jobs\SendSpringRevisionNotification;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class Create extends Component
{
    use AuthorizesRequests;

    public $springId;
    public $name;
    public $type;

    public $spring;

    public $saving = false;

    public function mount($springId)
    {
        $this->springId = $springId;
        $this->spring = Spring::find($this->springId);
        $this->authorize('update', $this->spring);

        $this->type = $this->spring->type;
        $this->name = $this->spring->name;
    }

    public function render()
    {
        $this->saving = false;

        return view('livewire.springs.create', [
            'waterSourceTypes' => Spring::TYPES,
        ]);
    }

    public function store(PatchSpringsAction $patchSprings)
    {
        $patchSprings($this->spring, [
            'type' => $this->type,
            'name' => $this->name,
        ]);

        return $this->redirect(duo_route(['spring' => $this->springId]));
    }
}
