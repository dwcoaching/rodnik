<?php

namespace App\Livewire;

use App\Models\Spring;
use Livewire\Component;
use Livewire\Attributes\Url;

class Duo extends Component
{
    #[Url(history: true)]
    public $page = [];

    public $firstRender;

    public function mount()
    {
        $this->page = array_merge(config('duo.url_defaults'), $this->page);
        $this->firstRender = true;
    }
    public function updatedPage()
    {
        // prevents unexisting array keys when the back button is used
        $this->page = array_merge(config('duo.url_defaults'), $this->page);
    }

    public function render()
    {
        $coordinates = [];

        if ($this->firstRender && $this->page['spring'] > 0) {
            $spring = Spring::find($this->page['spring']);

            if (! $spring) abort(404);

            $coordinates = [
                floatval($spring->longitude),
                floatval($spring->latitude)
            ];
        }

        return view('livewire.duo', compact('coordinates'));
    }
}
