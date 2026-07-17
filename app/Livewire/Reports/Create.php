<?php

declare(strict_types=1);

namespace App\Livewire\Reports;

use App\Enums\ReportQuality;
use App\Enums\ReportState;
use App\Jobs\SendReportNotification;
use App\Library\StatisticsService;
use App\Models\Photo;
use App\Models\Report;
use App\Models\Spring;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

final class Create extends Component
{
    use AuthorizesRequests;

    #[Locked]
    public $springId;

    #[Locked]
    public $reportId;

    public $sortablePhotos;

    #[Locked]
    public $sortedPhotos;

    public $state;

    public $quality;

    public $comment;

    public $visited_at;

    public $access_limited = false;

    public $littered = false;

    public $broken = false;

    protected $spring;

    protected $report;

    public function mount($springId, $reportId)
    {
        $this->springId = $springId;
        $this->reportId = $reportId;

        $this->spring = Spring::findOrFail($this->springId);

        if (! $this->reportId) {
            $this->visited_at = now()->format('Y-m-d');
            $this->sortedPhotos = collect();
            $this->sortablePhotos = [];
        } else {
            $this->report = Report::findOrFail($this->reportId);
            $this->authorize('update', $this->report);

            $this->state = $this->report->state?->value;
            $this->quality = $this->report->quality?->value;
            $this->access_limited = (bool) $this->report->access_limited;
            $this->littered = (bool) $this->report->littered;
            $this->broken = (bool) $this->report->broken;
            $this->comment = $this->report->comment;
            $this->visited_at = $this->report->visited_at?->format('Y-m-d');
            $this->sortedPhotos = $this->report->photos()->orderBy('order')->get();
            $this->sortablePhotos = $this->sortedPhotos->map(function ($item) {
                return [
                    'order' => $item->order,
                    'value' => $item->id,
                ];
            })->values()->all();
        }
    }

    public function render()
    {
        $this->spring = Spring::findOrFail($this->springId);

        return view('livewire.reports.create', [
            'photos' => $this->sortedPhotos ?? collect(),
            'spring' => $this->spring,
            'report' => $this->report,
        ]);
    }

    public function store()
    {
        if ($this->reportId) {
            $this->report = Report::findOrFail($this->reportId);
            $this->authorize('update', $this->report);
        } else {
            $this->authorize('create', Report::class);
            $this->report = new Report();
        }

        $this->validate();

        if (in_array($this->state, [ReportState::Dry->value, ReportState::NotFound->value], true)) {
            $this->quality = null;
        }

        if ($this->state === ReportState::NotFound->value) {
            $this->access_limited = false;
            $this->littered = false;
            $this->broken = false;
        }

        $this->report->spring_id = $this->springId;

        if (Auth::check()) {
            $this->report->user_id = Auth::user()->id;
        }

        $this->report->state = $this->state;
        $this->report->quality = $this->quality;
        $this->report->access_limited = $this->access_limited ? true : null;
        $this->report->littered = $this->littered ? true : null;
        $this->report->broken = $this->broken ? true : null;
        $this->report->comment = $this->comment;
        $this->report->visited_at = $this->visited_at;

        $this->report->save();

        $this->savePhotos($this->report->id);

        $this->report->spring->invalidateTiles();
        StatisticsService::invalidateReportsCount();

        if ($this->report->user) {
            $this->report->user->updateRating();
        }

        SendReportNotification::dispatch($this->report);

        return $this->redirect(duo_route(['spring' => $this->springId]));
    }

    public function getSortedPhotosFromSortablePhotos(int $reportId)
    {
        $sortablePhotos = collect($this->sortablePhotos)
            ->filter(fn ($item) => is_array($item)
                && isset($item['value'], $item['order'])
                && is_numeric($item['value']) && is_numeric($item['order']))
            ->values();

        return Photo::whereIn('id', $sortablePhotos->pluck('value')->all())->get()
            ->filter(function (Photo $photo) use ($reportId) {
                if ($photo->report_id !== null && (int) $photo->report_id === $reportId) {
                    return true;
                }

                return $photo->report_id === null;
            })
            ->sortBy(function (Photo &$photo) use ($sortablePhotos) {
                return $sortablePhotos->search(function ($item) use (&$photo) {
                    if ((int) $item['value'] === (int) $photo->id) {
                        $photo->order = (int) $item['order'];

                        return true;
                    }

                    return false;

                });
            });
    }

    public function savePhotos(int $reportId)
    {
        if ($this->reportId) {
            $this->authorize('update', $this->report);
        } else {
            $this->authorize('create', Report::class);
        }

        $this->sortedPhotos = $this->getSortedPhotosFromSortablePhotos($reportId);

        $storedPhotos = $this->report->photos;

        $photoIdsToDetach = $storedPhotos->diff($this->sortedPhotos)->pluck('id')->all();
        if ($photoIdsToDetach !== []) {
            Photo::query()
                ->whereIn('id', $photoIdsToDetach)
                ->update(['report_id' => null]);
        }

        $photoIdsToAttach = $this->sortedPhotos->diff($storedPhotos)->pluck('id')->all();
        if ($photoIdsToAttach !== []) {
            Photo::query()
                ->whereIn('id', $photoIdsToAttach)
                ->whereNull('report_id')
                ->update(['report_id' => $reportId]);
        }

        $upsertPhotos = $this->sortedPhotos->map(function ($item) {
            return [
                'id' => $item->id,
                'original_filename' => $item->original_filename,
                'original_extension' => $item->original_extension,
                'extension' => $item->extension,
                'order' => $item->order,
            ];
        });

        if ($upsertPhotos->count()) {
            Photo::upsert($upsertPhotos->all(), uniqueBy: ['id'], update: ['order']);
        }
    }

    protected function rules()
    {
        return [
            'visited_at' => 'nullable|date',
            'state' => [
                'nullable',
                Rule::enum(ReportState::class),
            ],
            'quality' => [
                'nullable',
                Rule::enum(ReportQuality::class),
            ],
            'comment' => 'nullable|string|max:65535',
            'access_limited' => 'nullable|boolean',
            'littered' => 'nullable|boolean',
            'broken' => 'nullable|boolean',
            'springId' => 'required|integer',
        ];
    }
}
