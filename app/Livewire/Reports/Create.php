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

    public $photosIds = [];

    #[Rule('image|max:10240')]
    public $file;

    protected $spring;
    protected $report;

    public $state;
    public $quality;
    public $comment;
    public $visited_at;

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
        } else {
            $this->report = Report::findOrFail($this->reportId);
            $this->authorize('update', $this->report);

            $this->state = $this->report->state;
            $this->quality = $this->report->quality;
            $this->comment = $this->report->comment;
            $this->visited_at = $this->report->visited_at->format('Y-m-d');
            $this->photosIds = $this->report->photos->pluck('id')->all();
        }
    }

    public function render()
    {
        $this->spring = Spring::findOrFail($this->springId);

        $photos = Photo::whereIn('id', $this->photosIds)->orderByDesc('id')->get();

        return view('livewire.reports.create', [
            'photos' => $photos,
            'spring' => $this->spring,
            'report' => $this->report,
        ]);
    }

    public function store()
    {
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

        $photos = Photo::whereIn('id', $this->photosIds)->orderByDesc('id')->get();

        foreach ($photos as $photo) {
            $photo->report_id = $this->report->id;
            $photo->save();
        }

        SendReportNotification::dispatch($this->report);

        return $this->redirect(route('springs.show', $this->springId), navigate: true);
    }

    public function updatedFile()
    {
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

        $photo->save();
        Storage::disk('photos')->put($photo->filename, $image->stream('jpg', 80));

        $this->photosIds[] = $photo->id;
    }

    public function removePhoto($photoId)
    {
        if ($this->report->id) {
            if (! Auth::check() || Auth::user()->cannot('update', $this->report)) {
                abort(403);
            }
        }

        if (! in_array($photoId, $this->photosIds)) {
            abort(403);
        }

        array_splice($this->photosIds, array_search($photoId, $this->photosIds), 1);

        $photo = Photo::find($photoId);
        $photo->report_id = null;
        $photo->save();
    }
}
