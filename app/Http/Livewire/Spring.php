<?php

namespace App\Http\Livewire;

use App\Models\Spring as SpringModel;
use Livewire\Component;

class Spring extends Component
{
    public $springId;

    public function render()
    {
        $spring = SpringModel::find($this->springId);
        return view('livewire.spring', compact('spring'));
    }
}
