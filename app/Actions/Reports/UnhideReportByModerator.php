<?php

namespace App\Actions\Reports;

use App\Library\StatisticsService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class UnhideReportByModerator
{
    public function handle($report)
    {
        $this->authorize();
        $this->validate();

        $report->hidden_at = null;
        $report->hidden_by_moderator_id = null;
        $report->save();

        $report->spring->invalidateTiles();
        if ($report->user_id) {
            $report->user->updateRating();
        }
        StatisticsService::invalidateReportsCount();
    }

    public function authorize(): void
    {
        Gate::authorize('admin');
    }

    public function validate(): void
    {

    }
}
