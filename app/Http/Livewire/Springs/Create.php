<?php

namespace App\Http\Livewire\Springs;

use App\Models\Report;
use App\Models\Spring;
use Livewire\Component;
use App\Rules\SpringTypeRule;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class Create extends Component
{
    public $spring;
    public $name;
    public $type;
    public $coordinates;

    protected function rules()
    {
        return [
            'name' => 'nullable',
            'type' => [new SpringTypeRule],
            'coordinates' => 'nullable',
        ];
    }

    public function mount(Spring $spring)
    {
        $this->spring = $spring ? $spring : new Spring();
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

        $coordinatesArray = explode(',', $this->coordinates);
        $latitude = $coordinatesArray[0];
        $longitude = $coordinatesArray[1];

        $springChangeCount = 0;
        $report = new Report();

        if ($this->spring->latitude != $latitude) {
            $report->old_latitude = $this->spring->latitude;
            $report->new_latitude = $latitude;
            $this->spring->latitude = $latitude;
            $springChangeCount++;
        }

        if ($this->spring->longitude != $longitude) {
            $report->old_longitude = $this->spring->longitude;
            $report->new_longitude = $longitude;
            $this->spring->longitude = $longitude;
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
                $report->user_id = Auth::check() ? Auth::user()->id : null;
                $report->spring_id = $this->spring->id;
                $report->spring_edit = true;
                $report->save();
            }

            $this->spring->save();
            $this->spring->invalidateTiles();
        }


        return redirect()->route('show', $this->spring);
    }
}
