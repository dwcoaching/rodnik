<?php

namespace App\Http\Livewire\Springs;

use App\Models\Spring;
use Livewire\Component;
use App\Rules\SpringTypeRule;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class Create extends Component
{
    public $spring;
    public $coordinates;



    protected function rules()
    {
        return [
            'spring.name' => 'nullable',
            'spring.type' => [new SpringTypeRule],
            'coordinates' => 'nullable',
        ];
    }

    public function mount(Spring $spring)
    {
        $this->spring = $spring ? $spring : new Spring();
    }

    public function render()
    {
        return view('livewire.springs.create');
    }

    public function store()
    {
        $this->validate();

        $coordinatesArray = explode(',', $this->coordinates);
        $this->spring->latitude = $coordinatesArray[0];
        $this->spring->longitude = $coordinatesArray[1];

        $this->user_id = Auth::check() ? Auth::user()->id : null;
        $this->spring->save();
        $this->spring->invalidateTiles();

        return redirect()->route('show', $this->spring);
    }
}
