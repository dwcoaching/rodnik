<?php

namespace App\Http\Livewire\Reports;

use App\Library\Exif;
use App\Models\Photo;
use App\Models\Report;
use Livewire\Component;
use App\Rules\SpringTypeRule;
use Livewire\WithFileUploads;
use Illuminate\Validation\Rule;
use App\Jobs\SendReportNotification;
use Illuminate\Support\Facades\Auth;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class Create extends Component
{
    use WithFileUploads, AuthorizesRequests;

    public $spring;
    public $report;
    public $photosIds = [];
    public $file;

    protected function rules() {
        return [
            'visited_at' => 'nullable|date',
            'report.state' => [
                'nullable',
                Rule::in(['dry', 'dripping', 'running', 'notfound'])
            ],
            'report.quality' => [
                'nullable',
                Rule::in(['bad', 'uncertain', 'good'])
            ],
            'report.comment' => 'nullable|string|max:65535',
            'report.spring_id' => 'required|integer',
        ];
    }

    public function mount($spring)
    {
        $this->spring = $spring;

        if (! $this->report) {
            $this->report = new Report();
            $this->report->spring_id = $this->spring->id;
            $this->visited_at = now()->format('Y-m-d');
        } else {
            $this->authorize('update', $this->report);

            $this->visited_at = $this->report->visited_at->format('Y-m-d');
            $this->photosIds = $this->report->photos->pluck('id')->all();
        }
    }

    public function render()
    {
        $photos = Photo::whereIn('id', $this->photosIds)->orderByDesc('id')->get();

        return view('livewire.reports.create', ['photos' => $photos]);
    }

    public function store()
    {
        $this->validate();

        if (in_array($this->report->state, ['dry', 'notfound'])) {
            $this->report->quality = null;
        }

        if ($this->report->id) {
            $this->authorize('update', $this->report);
        }

        if (Auth::check()) {
            $this->report->user_id = Auth::user()->id;
        }

        $this->report->visited_at = $this->visited_at;

        $this->report->save();

        $this->report->spring->invalidateTiles();

        if (Auth::check()) {
            Auth::user()->updateRating();
        }

        $photos = Photo::whereIn('id', $this->photosIds)->orderByDesc('id')->get();

        foreach ($photos as $photo) {
            $photo->report_id = $this->report->id;
            $photo->save();
        }

        SendReportNotification::dispatch($this->report);

        return redirect()->route('springs.show', $this->spring);
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
