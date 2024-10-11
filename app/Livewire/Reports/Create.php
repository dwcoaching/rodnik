<?php

namespace App\Livewire\Reports;

use App\Library\Exif;
use App\Models\Photo;
use App\Models\Report;
use App\Models\Spring;
use Livewire\Component;
use App\Rules\SpringTypeRule;
use Livewire\WithFileUploads;
use Illuminate\Validation\Rule;
use App\Library\StatisticsService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Jobs\SendReportNotification;
use Illuminate\Support\Facades\Auth;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class Create extends Component
{
    use WithFileUploads, AuthorizesRequests;

    #[Locked]
    public $springId;

    #[Locked]
    public $reportId;

    public $sortablePhotos;
    protected $sortedPhotos;

    #[Rule('image|max:10240')]
    public $file;

    protected $spring;
    protected $report;

    public $state;
    public $quality;
    public $comment;
    public $visited_at;

    public $not_found;
    public $no_access;
    public $difficult_access;

    protected function rules() {
        return [
            'visited_at' => 'nullable|date',
            'state' => [
                'nullable',
                Rule::in(['dry', 'dripping', 'running', 'notfound'])
            ],
            'quality' => [
                'nullable',
                Rule::in(['bad', 'uncertain', 'good'])
            ],
            'comment' => 'nullable|string|max:65535',
            'springId' => 'required|integer',
        ];
    }

    public function mount($springId, $reportId)
    {
        $this->springId = $springId;
        $this->reportId = $reportId;

        $this->spring = Spring::findOrFail($this->springId);

        if (! $this->reportId) {
            $this->visited_at = now()->format('Y-m-d');
            $this->sortedPhotos = collect();
            $this->sortablePhotos = collect();
        } else {
            $this->report = Report::findOrFail($this->reportId);
            $this->authorize('update', $this->report);

            $this->state = $this->report->state;
            $this->quality = $this->report->quality;
            $this->comment = $this->report->comment;
            $this->visited_at = $this->report->visited_at?->format('Y-m-d');
            $this->sortedPhotos = $this->report->photos()->orderBy('order')->get();
            $this->sortablePhotos = $this->sortedPhotos->map(function($item) {
                return [
                    'order' => $item->order,
                    'value' => $item->id
                ];
            });

            $this->not_found = false;
            $this->no_access = false;
            $this->difficult_access = false;
        }
    }

    public function render()
    {
        $this->spring = Spring::findOrFail($this->springId);

        return view('livewire.reports.create', [
            'photos' => $this->sortedPhotos,
            'spring' => $this->spring,
            'report' => $this->report,
        ]);
    }

    public function store()
    {
        if (! Auth::check()) {
            abort(401);
        }

        $this->validate();

        if ($this->reportId) {
            $this->report = Report::findOrFail($this->reportId);
        } else {
            $this->report = new Report();
        }

        $this->report->spring_id = $this->springId;

        if (in_array($this->state, ['dry', 'notfound'])) {
            $this->quality = null;
        }

        if ($this->reportId) {
            $this->authorize('update', $this->report);
        }

        if (Auth::check()) {
            $this->report->user_id = Auth::user()->id;
        }

        $this->report->state = $this->state;
        $this->report->quality = $this->quality;
        $this->report->comment = $this->comment;
        $this->report->visited_at = $this->visited_at;

        $this->report->save();

        $this->report->spring->invalidateTiles();
        StatisticsService::invalidateReportsCount();

        if ($this->report->user) {
            $this->report->user->updateRating();
        }

        $this->savePhotos();

        SendReportNotification::dispatch($this->report);

        return $this->redirect(route('springs.show', $this->springId));
    }

    public function updatedFile()
    {
        if (! Auth::check()) {
            abort(401);
        }

        $this->validate([
            'file' => 'image|max:10240', // 10MB Max
        ]);

        $photo = new Photo();
        $photo->original_extension = $this->file->getClientOriginalExtension();
        $photo->original_filename = $this->file->getClientOriginalName();
        $photo->extension = $this->file->extension();

        $image = Image::make($this->file)->orientate();
        $photo->width = $image->width();
        $photo->height = $image->height();

        $image->resize(1280, 1280, function ($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
        });

        $exif = new Exif($this->file);

        $photo->latitude = $exif->latitude();
        $photo->longitude = $exif->longitude();
        $photo->order = $this->getMaxPhotoOrder() + 1;
        $photo->save();
        Storage::disk('photos')->put($photo->filename, $image->stream('jpg', 80));

        $this->sortablePhotos->push([
            'value' => $photo->id,
            'order' => $photo->order,
        ]);

        $this->sortedPhotos = $this->getSortedPhotosFromSortablePhotos();
    }

    public function getMaxPhotoOrder()
    {
        return $this->sortablePhotos->max('order');
    }

    public function removePhoto($photoId)
    {
        if (! Auth::check()) {
            abort(401);
        }

        $this->report = Report::find($this->reportId);

        if ($this->report) {
            if (! Auth::check() || Auth::user()->cannot('update', $this->report)) {
                abort(403);
            }
        }

        $photoIndex = $this->sortablePhotos->search(function ($item) use ($photoId) {
            return $item['value'] == $photoId;
        });

        if ($photoIndex === false) {
            abort(403);
        } else {
            $this->sortablePhotos->splice($photoIndex, 1);
        }

        $this->sortedPhotos = $this->getSortedPhotosFromSortablePhotos();
    }

    public function updateImageSort($sortedPhotosInBrowser)
    {
        $this->sortablePhotos = collect($sortedPhotosInBrowser);
        $this->sortedPhotos = $this->getSortedPhotosFromSortablePhotos();
    }

    public function getSortedPhotosFromSortablePhotos()
    {
        return Photo::whereIn('id', $this->sortablePhotos->pluck('value')->all())->get()
            ->sortBy(function(Photo &$photo) {
                return $this->sortablePhotos->search(function ($item) use (&$photo) {
                    if ($item['value'] == $photo->id) {
                        $photo->order = $item['order'];
                        return true;
                    } else {
                        return false;
                    }
                });
            });
    }

    public function savePhotos()
    {
        $this->sortedPhotos = $this->getSortedPhotosFromSortablePhotos();

        $storedPhotos = $this->report->photos;

        $photosToDetach = $storedPhotos->diff($this->sortedPhotos);
        if ($photosToDetach->count()) {
            $photosToDetach->toQuery()->update(['report_id' => null]);
        }

        $photosToAttach = $this->sortedPhotos->diff($storedPhotos);
        if ($photosToAttach->count()) {
            $photosToAttach->toQuery()
                ->whereNull('report_id')
                ->update(['report_id' => $this->report->id]);
        }

        Photo::upsert(
            $this->sortedPhotos->map(function ($item) {
                return [
                    'id' => $item->id,
                    'original_filename' => $item->original_filename,
                    'original_extension' => $item->original_extension,
                    'extension' => $item->extension,
                    'order' => $item->order,
                ];
            })->all(), uniqueBy: ['id'], update: ['order']);
    }
}
