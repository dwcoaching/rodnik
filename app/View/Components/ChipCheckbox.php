<?php

namespace App\View\Components;

use Illuminate\View\Component;

class ChipCheckbox extends Component
{
    public $name;
    public $key;

    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct($name, $key)
    {
        //
        $this->name = $name;
        $this->key = $key;
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        return view('components.chip-checkbox');
    }
}
