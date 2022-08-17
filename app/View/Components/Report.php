<?php

namespace App\View\Components;

use App\Models\Report as ReportModel;
use Illuminate\View\Component;

class Report extends Component
{
    public $report;
    public $hasName;

    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct(ReportModel $report, $hasName)
    {
        $this->report = $report;
        $this->hasName = $hasName == 'true' ? true : false;
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        return view('components.report');
    }
}
