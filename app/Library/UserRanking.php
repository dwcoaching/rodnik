<?php

declare(strict_types=1);

namespace App\Library;

use App\Models\Report;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

final class UserRanking
{
    /** @return LengthAwarePaginator<int, User> */
    public function paginate(): LengthAwarePaginator
    {
        $reportCounts = Report::query()
            ->visible()
            ->has('spring')
            ->select('user_id')
            ->selectRaw('COUNT(*) as reports_count, COUNT(DISTINCT spring_id) as springs_count')
            ->groupBy('user_id');

        return User::query()
            ->joinSub($reportCounts, 'report_counts', 'users.id', '=', 'report_counts.user_id')
            ->select(['users.id', 'users.name', 'report_counts.reports_count', 'report_counts.springs_count'])
            ->orderByDesc('reports_count')
            ->orderBy('users.id')
            ->paginate(100);
    }
}
