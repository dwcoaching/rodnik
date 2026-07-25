<?php

declare(strict_types=1);

namespace App\Actions\Reports;

use App\Library\ReportSpringMover;
use App\Models\Report;
use App\Models\Spring;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class MoveReportBackToRedirectedSourceAction
{
    public function __construct(
        private ReportSpringMover $mover,
    ) {}

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
                'source_spring_id' => __('ui.actions.source_missing_or_hidden'),
            ]);
        }

        $mergeTarget = $source->visibleMergeTargetForReports();

        if (! $mergeTarget || (int) $mergeTarget->id !== (int) $report->spring_id) {
            throw ValidationException::withMessages([
                'source_spring_id' => __('ui.actions.source_not_redirecting_to_current'),
            ]);
        }

        return $source;
    }

    public function execute(Report $report, Spring $source): Report
    {
        return $this->mover->move($report, $source);
    }
}
