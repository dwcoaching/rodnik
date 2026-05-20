<?php

namespace App\Actions\Reports;

use App\Models\Report;
use App\Models\Spring;
use App\Library\StatisticsService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class MoveReportToSpringAction
{
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

        $target = Spring::find($targetSpringId);

        if (! $target || $target->hidden_at) {
            throw ValidationException::withMessages([
                'target_spring_id' => 'Target water source does not exist or is hidden.',
            ]);
        }

        if ((int) $report->spring_id === (int) $target->id) {
            throw ValidationException::withMessages([
                'target_spring_id' => 'Report is already attached to this water source.',
            ]);
        }

        return $target;
    }

    public function execute(Report $report, Spring $target): Report
    {
        $source = $report->spring;

        $report->spring_id = $target->id;
        $report->save();

        $source?->invalidateTiles();
        $target->invalidateTiles();

        if ($report->user_id) {
            $report->user->updateRating();
        }

        StatisticsService::invalidateReportsCount();

        return $report->fresh(['spring', 'user', 'photos']);
    }
}
