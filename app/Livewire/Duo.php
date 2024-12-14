<?php

namespace App\Livewire;

use App\Models\Spring;
use Livewire\Component;
use Livewire\Attributes\Url;

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
        $coordinates = [];

        if ($this->firstRender && $this->springId > 0) {
            $spring = Spring::findOrFail($this->springId);

            $coordinates = [
                floatval($spring->longitude),
                floatval($spring->latitude)
            ];
        }

        return view('livewire.duo', compact('coordinates'));
    }
}
