<?php

namespace App\Livewire;

use Livewire\Attributes\Url;
use Livewire\Component;

class Duo extends Component
{
    #[Url(as: 's', history: true)]
    public $springId = null;

    #[Url(as: 'u', history: true)]
    public $userId = null;

    #[Url(as: 'location', history: true)]
    public $location = false;

    public $firstRender;

    public function mount()
    {
        $this->firstRender = true;
    }

    public function render()
    {
        return view('livewire.duo');
    }
}
