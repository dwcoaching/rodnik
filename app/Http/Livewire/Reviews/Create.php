<?php

namespace App\Http\Livewire\Reviews;

use Livewire\Component;

class Create extends Component
{
    public $spring;

    public function mount($spring)
    {
        $this->spring = $spring;
    }

    public function render()
    {
        return view('livewire.reviews.create');
    }
}
