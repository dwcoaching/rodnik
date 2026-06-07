<?php

namespace App\View\Components;

use Illuminate\View\View;
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
     * @return View
     */
    public function render()
    {
        return view('components.navbar');
    }
}
