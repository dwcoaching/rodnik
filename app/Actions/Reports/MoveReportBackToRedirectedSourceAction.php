<?php

namespace App\Actions\Reports;

use App\Models\Report;
use App\Models\Spring;
use App\Library\ReportSpringMover;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class MoveReportBackToRedirectedSourceAction
{
    public function __construct(
        protected ReportSpringMover $mover,
    ) {
    }

    public function __invoke(Report $report, $sourceSpringId): Report
    {
        $this->authorize();
        $source = $this->validate($report, $sourceSpringId);

        return $this->execute($report, $source);
    }

    public function authorize(): void
    {
        Gate::authorize('admin');
    }

    public function validate(Report $report, $sourceSpringId): Spring
    {
        Validator::make(
            ['source_spring_id' => $sourceSpringId],
            ['source_spring_id' => ['required', 'integer']],
        )->validate();

        $source = Spring::find($sourceSpringId);

        if (! $source || $source->hidden_at) {
            throw ValidationException::withMessages([
                'source_spring_id' => 'Source water source does not exist or is hidden.',
            ]);
        }

        $mergeTarget = $source->visibleMergeTargetForReports();

        if (! $mergeTarget || (int) $mergeTarget->id !== (int) $report->spring_id) {
            throw ValidationException::withMessages([
                'source_spring_id' => 'Report can only be moved back to a water source that redirects to its current source.',
            ]);
        }

        return $source;
    }

    public function execute(Report $report, Spring $source): Report
    {
        return $this->mover->move($report, $source);
    }
}
