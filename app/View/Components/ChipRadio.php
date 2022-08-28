<?php

namespace App\View\Components;

use Illuminate\View\Component;

class ChipRadio extends Component
{
    public $name;
    public $key;
    public $value;

    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct($name, $key, $value)
    {
        //
        $this->name = $name;
        $this->key = $key;
        $this->value = $value;
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        return view('components.chip-radio');
    }
}
