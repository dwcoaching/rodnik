<?php

declare(strict_types=1);

namespace App\Actions\Reports;

use App\Library\ReportSpringMover;
use App\Models\Report;
use App\Models\Spring;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class MoveReportToMergeTargetAction
{
    public function __construct(
        private ReportSpringMover $mover,
    ) {}

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
                'target_spring_id' => __('ui.actions.report_has_no_merge_target'),
            ]);
        }

        return $target;
    }

    public function execute(Report $report, Spring $target): Report
    {
        return $this->mover->move($report, $target);
    }
}
