<?php

declare(strict_types=1);

namespace App\Actions\Reports;

use App\Library\ReportSpringMover;
use App\Models\Report;
use App\Models\Spring;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class TransferReportToSpringAction
{
    public function __construct(
        private ReportSpringMover $mover,
    ) {}

    public function __invoke(Report $report, $targetSpringId): Report
    {
        $this->authorize();
        $target = $this->validate($report, $targetSpringId);

        return $this->execute($report, $target);
    }

    public function authorize(): void
    {
        Gate::authorize('admin');
    }

    public function validate(Report $report, $targetSpringId): Spring
    {
        Validator::make(
            ['target_spring_id' => $targetSpringId],
            ['target_spring_id' => ['required', 'integer']],
        )->validate();

        if ((int) $report->spring_id === (int) $targetSpringId) {
            throw ValidationException::withMessages([
                'target_spring_id' => __('ui.actions.report_already_attached'),
            ]);
        }

        $target = Spring::find($targetSpringId);

        if (! $target || $target->hidden_at) {
            throw ValidationException::withMessages([
                'target_spring_id' => __('ui.actions.target_missing_or_hidden'),
            ]);
        }

        if ($target->redirect_to_spring_id) {
            throw ValidationException::withMessages([
                'target_spring_id' => __('ui.actions.target_redirected'),
            ]);
        }

        return $target;
    }

    public function execute(Report $report, Spring $target): Report
    {
        return $this->mover->move($report, $target);
    }
}
