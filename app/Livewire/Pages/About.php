<?php

namespace App\Livewire\Pages;

use Livewire\Component;
use App\View\Components\AppLayout;

class About extends Component
{
    public function render()
    {
        return view('livewire.pages.about')
            ->layout(AppLayout::class);
    }
}
