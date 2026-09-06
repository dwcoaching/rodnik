<?php

declare(strict_types=1);

namespace App\Livewire\Duo\Reports;

use App\Enums\ReportQuality;
use App\Library\StatisticsService;
use App\Library\TrackPolygonArea;
use App\Models\Report;
use App\Models\TrackPolygon;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Reactive;
use Livewire\Component;

final class Index extends Component
{
    private const SOURCE_TYPES = [
        'spring' => 'Spring',
        'water_well' => 'Water well',
        'water_tap' => 'Water tap',
        'drinking_water' => 'Drinking water source',
        'fountain' => 'Fountain',
        'other' => 'Water source',
    ];

    #[Reactive]
    public $userId;

    #[Locked]
    public int $limit = 12;

    /** @var array{west: float, south: float, east: float, north: float}|null */
    #[Locked]
    public ?array $bounds = null;

    /** @var array{spring: bool, water_well: bool, water_tap: bool, drinking_water: bool, fountain: bool, other: bool, confirmed: bool, along: bool} */
    #[Locked]
    public array $filters = [
        'spring' => true,
        'water_well' => true,
        'water_tap' => true,
        'drinking_water' => true,
        'fountain' => true,
        'other' => true,
        'confirmed' => false,
        'along' => false,
    ];

    #[Locked]
    public ?string $trackPolygonHash = null;

    /** @var array{polygon: TrackPolygon, area: TrackPolygonArea}|null */
    private ?array $trackContext = null;

    public function updateBounds(array $bounds): void
    {
        $this->updateMap($bounds, $this->filters, $this->trackPolygonHash);
    }

    public function updateMap(array $bounds, array $filters, ?string $trackPolygonHash = null): void
    {
        if ($this->userId || ($filters['along'] ?? null) === false) {
            $trackPolygonHash = null;
        }

        $rules = [
            'bounds' => ['required', 'array:west,south,east,north'],
            'bounds.west' => ['required', 'numeric', 'between:-180,180'],
            'bounds.east' => ['required', 'numeric', 'between:-180,180'],
            'bounds.south' => ['required', 'numeric', 'between:-90,90'],
            'bounds.north' => ['required', 'numeric', 'between:-90,90', 'gte:bounds.south'],
            'filters' => ['required', 'array:'.implode(',', [...array_keys(self::SOURCE_TYPES), 'confirmed', 'along'])],
            'trackPolygonHash' => ['nullable', 'string', 'regex:/\A[a-f0-9]{64}\z/'],
        ];

        foreach ([...array_keys(self::SOURCE_TYPES), 'confirmed', 'along'] as $key) {
            $rules['filters.'.$key] = ['required', 'boolean:strict'];
        }

        $validated = Validator::make(compact('bounds', 'filters', 'trackPolygonHash'), $rules)->validate();
        $bounds = array_intersect_key($validated['bounds'], array_flip(['west', 'south', 'east', 'north']));
        $filters = array_replace($this->filters, $validated['filters']);
        $trackPolygonHash = $validated['trackPolygonHash'];

        if ($trackPolygonHash !== null) {
            $this->resolveTrack($trackPolygonHash);
        }

        $this->resetValidation();

        if ($this->bounds !== $bounds || $this->filters !== $filters || $this->trackPolygonHash !== $trackPolygonHash) {
            $this->bounds = $bounds;
            $this->filters = $filters;
            $this->trackPolygonHash = $trackPolygonHash;
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
            $lastReports = $this->mapReports();

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

    /** @return Collection<int, Report> */
    private function mapReports(): Collection
    {
        if ($this->bounds === null || ($this->filters['along'] && $this->trackPolygonHash === null)) {
            return new Collection;
        }

        $query = Report::query()->visible()
            ->join('springs', 'springs.id', '=', 'reports.spring_id')
            ->whereNull('springs.hidden_at')
            ->whereNull('springs.redirect_to_spring_id')
            ->whereBetween('springs.latitude', [$this->bounds['south'], $this->bounds['north']])
            ->where(function (Builder $query): void {
                if ($this->bounds['west'] > $this->bounds['east']) {
                    $query->where('springs.longitude', '>=', $this->bounds['west'])
                        ->orWhere('springs.longitude', '<=', $this->bounds['east']);
                } else {
                    $query->whereBetween('springs.longitude', [$this->bounds['west'], $this->bounds['east']]);
                }
            });

        $disabledTypes = array_values(array_filter(self::SOURCE_TYPES, fn (string $key): bool => ! $this->filters[$key], ARRAY_FILTER_USE_KEY));

        if ($disabledTypes !== []) {
            $query->where(fn (Builder $query) => $query->whereNull('springs.type')->orWhereNotIn('springs.type', $disabledTypes));
        }

        if ($this->filters['confirmed']) {
            $query->whereIn('reports.spring_id', Report::query()->visible()
                ->select('spring_id')->groupBy('spring_id')
                ->havingRaw('SUM(CASE WHEN quality = ? THEN 1 WHEN quality IS NOT NULL THEN -1 ELSE 0 END) > 0', [ReportQuality::Good->value]));
        }

        $query->latest('reports.created_at')->latest('reports.id');

        if (! $this->filters['along']) {
            return $query->select('reports.*')->limit($this->limit + 1)
                ->with(['spring', 'user', 'photos'])->get();
        }

        ['polygon' => $polygon, 'area' => $area] = $this->resolveTrack($this->trackPolygonHash);
        $query->whereBetween('springs.latitude', [$polygon->latitude_from, $polygon->latitude_to])
            ->whereBetween('springs.longitude', [$polygon->longitude_from, $polygon->longitude_to]);

        $ids = $this->matchingReportIds($query, $area);

        return $query->select('reports.*')->whereIn('reports.id', $ids)
            ->with(['spring', 'user', 'photos'])->get();
    }

    /**
     * @param  Builder<Report>  $query
     * @return list<int>
     */
    private function matchingReportIds(Builder $query, TrackPolygonArea $area): array
    {
        $ids = [];
        $matches = [];
        $last = null;

        do {
            $batch = clone $query;

            if ($last !== null) {
                $batch->where(function (Builder $query) use ($last): void {
                    if ($last->created_at === null) {
                        $query->whereNull('reports.created_at')->where('reports.id', '<', $last->id);
                    } else {
                        $query->where('reports.created_at', '<', $last->created_at)
                            ->orWhereNull('reports.created_at')
                            ->orWhere(fn (Builder $query) => $query->where('reports.created_at', $last->created_at)->where('reports.id', '<', $last->id));
                    }
                });
            }

            $candidates = $batch->select(['reports.id', 'reports.spring_id', 'reports.created_at', 'springs.longitude', 'springs.latitude'])
                ->limit(250)->toBase()->get();

            foreach ($candidates as $candidate) {
                $matches[$candidate->spring_id] ??= $area->contains((float) $candidate->longitude, (float) $candidate->latitude);

                if ($matches[$candidate->spring_id]) {
                    $ids[] = (int) $candidate->id;

                    if (count($ids) > $this->limit) {
                        return $ids;
                    }
                }
            }

            $last = $candidates->last();
        } while ($candidates->count() === 250);

        return $ids;
    }

    /** @return array{polygon: TrackPolygon, area: TrackPolygonArea} */
    private function resolveTrack(string $hash): array
    {
        if (($this->trackContext['polygon']->hash ?? null) === $hash) {
            return $this->trackContext;
        }

        $polygon = TrackPolygon::query()->where('hash', $hash)->first();

        if ($polygon === null) {
            throw ValidationException::withMessages(['trackPolygonHash' => ['The uploaded track polygon is unavailable.']]);
        }

        try {
            $area = TrackPolygonArea::fromArray($polygon->polygon);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['trackPolygonHash' => ['The uploaded track polygon is invalid.']]);
        }

        return $this->trackContext = compact('polygon', 'area');
    }
}
