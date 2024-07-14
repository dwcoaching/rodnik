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

    public $spring;

    public $saving = false;

    protected function rules()
    {
        return [
            'name' => 'nullable',
            'type' => [new SpringTypeRule],
        ];
    }

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
