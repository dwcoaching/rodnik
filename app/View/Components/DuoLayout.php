<?php

declare(strict_types=1);

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

final class DuoLayout extends Component
{
    /** @param array<string, mixed>|null $sharedMap */
    public function __construct(public ?array $sharedMap = null, public bool $missingMap = false) {}

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
