<?php

namespace App\Actions\Reports;

use App\Models\Report;
use App\Models\Spring;
use App\Library\ReportSpringMover;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class MoveReportToMergeTargetAction
{
    public function __construct(
        protected ReportSpringMover $mover,
    ) {
    }

    public function __invoke(Report $report): Report
    {
        $this->authorize();
        $target = $this->validate($report);

        return $this->execute($report, $target);
    }

    public function authorize(): void
    {
        Gate::authorize('admin');
    }

    public function validate(Report $report): Spring
    {
        $report->loadMissing('spring');
        $target = $report->spring?->visibleMergeTargetForReports();

        if (! $target) {
            throw ValidationException::withMessages([
                'target_spring_id' => 'Report is not attached to a merged water source with an eligible target.',
            ]);
        }

        return $target;
    }

    public function execute(Report $report, Spring $target): Report
    {
        return $this->mover->move($report, $target);
    }
}
