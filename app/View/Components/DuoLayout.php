<?php

namespace App\View\Components;

use Illuminate\View\View;
use Illuminate\View\Component;

class DuoLayout extends Component
{
    public function __construct()
    {

    }

    /**
     * Get the view / contents that represents the component.
     *
     * @return View
     */
    public function render()
    {
        return view('layouts.duo');
    }
}
