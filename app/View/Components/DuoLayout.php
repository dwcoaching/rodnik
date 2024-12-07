<?php

namespace App\View\Components;

use Illuminate\View\Component;

class DuoLayout extends Component
{
    public function __construct()
    {

    }

    /**
     * Get the view / contents that represents the component.
     *
     * @return \Illuminate\View\View
     */
    public function render()
    {
        return view('layouts.duo');
    }
}
