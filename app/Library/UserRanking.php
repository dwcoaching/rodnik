<?php

declare(strict_types=1);

namespace App\Library;

use App\Models\Report;
use App\Models\User;
use App\Support\PageNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

final class UserRanking
{
    /** @return LengthAwarePaginator<int, User> */
    public function paginate(): LengthAwarePaginator
    {
        return $this->query()->paginate(100, page: PageNumber::from(request()));
    }

    /** @return Collection<int, User> */
    public function top(int $limit): Collection
    {
        return $this->query()->limit($limit)->get();
    }

    public function count(): int
    {
        return $this->query()->toBase()->getCountForPagination();
    }

    /** @return Builder<User> */
    private function query(): Builder
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
            ->orderBy('users.id');
    }
}
