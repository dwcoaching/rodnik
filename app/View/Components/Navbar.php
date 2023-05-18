<?php

namespace App\View\Components;

use Illuminate\View\Component;

class Navbar extends Component
{
    public $map;

    public function __construct($map = null)
    {
        $this->map = $map;
    }

    /**
     * Get the view / contents that represents the component.
     *
     * @return \Illuminate\View\View
     */
    public function render()
    {
        return view('components.navbar');
    }
}
