<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Models\Report;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

final class ReportConditionBadges extends Component
{
    public function __construct(public Report $report) {}

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View
    {
        return view('components.report-condition-badges');
    }
}
