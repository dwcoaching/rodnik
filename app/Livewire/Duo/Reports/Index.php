<?php

declare(strict_types=1);

namespace App\Livewire\Duo\Reports;

use App\Library\StatisticsService;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Reactive;
use Livewire\Component;

final class Index extends Component
{
    #[Reactive]
    public $userId;

    #[Locked]
    public int $limit = 12;

    /** @var array{west: float, south: float, east: float, north: float}|null */
    #[Locked]
    public ?array $bounds = null;

    public function updateBounds(array $bounds): void
    {
        $bounds = Validator::make(['bounds' => $bounds], [
            'bounds' => ['required', 'array:west,south,east,north'],
            'bounds.west' => ['required', 'numeric', 'between:-180,180'],
            'bounds.east' => ['required', 'numeric', 'between:-180,180'],
            'bounds.south' => ['required', 'numeric', 'between:-90,90'],
            'bounds.north' => ['required', 'numeric', 'between:-90,90', 'gte:bounds.south'],
        ])->validate()['bounds'];

        if ($this->bounds !== $bounds) {
            $this->bounds = $bounds;
            $this->limit = $this->userId ? 12 : 24;
        }
    }

    public function showMore(): void
    {
        if ($this->bounds && ! $this->userId) {
            $this->limit += 24;
        }
    }

    public function render()
    {
        $springsCount = null;
        $reportsCount = null;
        $user = null;
        $hasMore = false;

        if ($this->userId) {
            if (! $user = User::find($this->userId)) {
                abort(404);
            }

            $lastReports = $user->reports()
                ->select('reports.*')
                ->with(['photos', 'user', 'spring'])
                ->whereNull('reports.hidden_at')
                ->join('springs', 'springs.id', '=', 'reports.spring_id')
                ->whereNull('springs.hidden_at')
                ->latest('reports.created_at')
                ->limit($this->limit)
                ->get();
        } else {
            $lastReports = Report::select('reports.*')
                ->join('springs', 'springs.id', '=', 'reports.spring_id')
                ->whereNull('reports.hidden_at')
                ->whereNull('reports.from_osm')
                ->whereNull('springs.hidden_at')
                ->whereNull('springs.redirect_to_spring_id')
                ->when($this->bounds, function (Builder $query, array $bounds): void {
                    $query->whereBetween('springs.latitude', [$bounds['south'], $bounds['north']])
                        ->where(function (Builder $query) use ($bounds): void {
                            if ($bounds['west'] > $bounds['east']) {
                                $query->where('springs.longitude', '>=', $bounds['west'])
                                    ->orWhere('springs.longitude', '<=', $bounds['east']);
                            } else {
                                $query->whereBetween('springs.longitude', [$bounds['west'], $bounds['east']]);
                            }
                        });
                }, fn (Builder $query) => $query->whereRaw('1 = 0'))
                ->latest('reports.created_at')
                ->latest('reports.id')
                ->limit($this->limit + 1)
                ->with(['spring', 'user', 'photos'])
                ->get();

            $hasMore = $lastReports->count() > $this->limit;
            $lastReports = $lastReports->take($this->limit);

            $springsCount = StatisticsService::getSpringsCount();
            $reportsCount = StatisticsService::getReportsCount();
        }

        return view('livewire.duo.reports.index', compact(
            'user',
            'lastReports',
            'springsCount',
            'reportsCount',
            'hasMore',
        ));
    }
}
