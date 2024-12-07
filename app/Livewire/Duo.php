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

    #[Url(as: 'locating', history: true)]
    public $locationMode = false;

    public function render()
    {
        return view('livewire.duo');
    }
}
