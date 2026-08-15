<?php

declare(strict_types=1);

namespace App\Actions;

use App\Library\StatisticsService;
use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

final class HideReportAction
{
    public function __invoke(User $user, Report $report): Report
    {
        $this->authorize($user, $report);
        $this->validate($report);

        return $this->execute($user, $report);
    }

    public function authorize(User $user, Report $report): void
    {
        Gate::forUser($user)->authorize('delete', $report);
    }

    public function validate(Report $report): void
    {
        abort_if($report->spring_edit || $report->from_osm, 403);
    }

    public function execute(User $user, Report $report): Report
    {
        if ($report->hidden_at !== null) {
            return $report;
        }

        $report->hidden_at = now();
        $report->hidden_by_author_id = $user->id;
        $report->save();

        $report->spring()->firstOrFail()->invalidateTiles();
        $user->updateRating();
        StatisticsService::invalidateReportsCount();

        return $report;
    }
}
