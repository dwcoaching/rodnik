<?php

namespace App\Http\Livewire\Reports;

use App\Library\Exif;
use App\Models\Photo;
use App\Models\Report;
use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Intervention\Image\Facades\Image;

class Create extends Component
{
    use WithFileUploads;

    public $spring;
    public $report;
    public $photosIds = [];
    public $file;

    protected function rules() {
        return [
            'report.visited_at' => 'nullable|date',
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

        if ($this->report == null) {
            $this->report = new Report();
            $this->report->spring_id = $this->spring->id;
            $this->report->visited_at = now()->format('Y-m-d');
        } else {
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

        if (Auth::check()) {
            $this->report->user_id = Auth::user()->id;
        }
        $this->report->save();

        $photos = Photo::whereIn('id', $this->photosIds)->orderByDesc('id')->get();

        foreach ($photos as $photo) {
            $photo->report_id = $this->report->id;
            $photo->save();
        }

        $this->report->spring->invalidateTiles();

        return redirect()->route('show', ['springId' => $this->spring->id]);
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

        $image = Image::make($this->file);
        $photo->width = $image->width();
        $photo->height = $image->height();

        $exif = new Exif($this->file);

        $photo->latitude = $exif->latitude();
        $photo->longitude = $exif->longitude();

        $photo->save();

        $this->file->storeAs('/', $photo->filename, 'photos');

        $this->photosIds[] = $photo->id;
    }

    public function removePhoto($photoId)
    {
        if (! Auth::check() || Auth::user()->cannot('update', $this->report)) {
            abort(403);
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
