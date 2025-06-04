<?php

namespace App\Livewire;

use App\Models\Spring;
use Livewire\Component;
use Livewire\Attributes\Url;

class Duo extends Component
{
    // #[Url(as: 's', history: true)]
    // public $springId = null;

    // #[Url(as: 'u', history: true)]
    // public $userId = null;

    // #[Url(as: 'location', history: true)]
    // public $location = false;

    #[Url(history: true)]
    public $view = [
        'spring' => null,
        'user' => null,
        'location' => false,
    ];

    public $firstRender;

    public function mount()
    {
        $this->firstRender = true;
    }

    public function render()
    {
        $coordinates = [];

        if ($this->firstRender && $this->view['spring'] > 0) {
            $spring = Spring::find($this->view['spring']);

            if (! $spring) abort(404);

            $coordinates = [
                floatval($spring->longitude),
                floatval($spring->latitude)
            ];
        }

        return view('livewire.duo', compact('coordinates'));
    }
}
