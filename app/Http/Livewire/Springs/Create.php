<?php

namespace App\Http\Livewire\Springs;

use App\Models\Report;
use App\Models\Spring;
use Livewire\Component;
use App\Rules\LatitudeRule;
use App\Rules\LongitudeRule;
use App\Rules\SpringTypeRule;
use Illuminate\Validation\Rule;
use App\Jobs\SendReportNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class Create extends Component
{
    use AuthorizesRequests;

    public $spring;
    public $name;
    public $type;
    public $coordinates;
    public $latitude;
    public $longitude;

    protected function rules()
    {
        return [
            'name' => 'nullable',
            'type' => [new SpringTypeRule],
            'latitude' => [new LatitudeRule],
            'longitude' => [new LongitudeRule],
        ];
    }

    public function mount(Spring $spring)
    {
        if ($spring) {
            $this->authorize('update', $spring);
            $this->spring = $spring;
        } else {
            $this->authorize('create', Spring::class);
            $this->spring = new Spring();
        }

        $this->coordinates = $this->spring->latitude . ', ' . $this->spring->longitude;
        $this->type = $this->spring->type;
        $this->name = $this->spring->name;
    }

    public function render()
    {
        return view('livewire.springs.create');
    }

    public function store()
    {
        $this->validate();

        $springChangeCount = 0;
        $report = new Report();

        if ($this->spring->latitude != $this->latitude) {
            $report->old_latitude = $this->spring->latitude;
            $report->new_latitude = $this->latitude;
            $this->spring->latitude = $this->latitude;
            $springChangeCount++;
        }

        if ($this->spring->longitude != $this->longitude) {
            $report->old_longitude = $this->spring->longitude;
            $report->new_longitude = $this->longitude;
            $this->spring->longitude = $this->longitude;
            $springChangeCount++;
        }

        if ($this->spring->name != $this->name) {
            $report->old_name = $this->spring->name;
            $report->new_name = $this->name;
            $this->spring->name = $this->name;
            $springChangeCount++;
        }

        if ($this->spring->type != $this->type) {
            $report->old_type = $this->spring->type;
            $report->new_type = $this->type;
            $this->spring->type = $this->type;
            $springChangeCount++;
        }

        if ($springChangeCount) {
            if ($this->spring->id) {
                $this->authorize('update', $this->spring);

                $report->user_id = Auth::check() ? Auth::user()->id : null;
                $report->spring_id = $this->spring->id;
                $report->spring_edit = true;
                $report->save();

                SendReportNotification::dispatch($report);
            } else {
                $this->authorize('create', Spring::class);
            }

            $this->spring->save();
            $this->spring->invalidateTiles();
        }

        return redirect()->route('springs.show', $this->spring);
    }
}
