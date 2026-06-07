<?php

namespace App\View\Components;

use Illuminate\View\View;
use Illuminate\View\Component;

class AppLayout extends Component
{
    public $navbar;
    public $map;

    public function __construct($navbar = null, $map = null)
    {
        $this->navbar = $navbar;
        $this->map = $map;
    }

    /**
     * Get the view / contents that represents the component.
     *
     * @return View
     */
    public function render()
    {
        return view('layouts.app');
    }
}
