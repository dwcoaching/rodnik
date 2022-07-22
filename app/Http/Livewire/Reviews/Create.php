<?php

namespace App\Http\Livewire\Reviews;

use App\Library\Exif;
use App\Models\Photo;
use App\Models\Review;
use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class Create extends Component
{
    use WithFileUploads;

    public $spring;
    public $review;
    public $photosIds = [];
    public $file;

    protected function rules() {
        return [
            'review.visited_at' => 'nullable|date',
            'review.state' => [
                'nullable',
                Rule::in(['dry', 'dripping', 'running'])
            ],
            'review.quality' => [
                'nullable',
                Rule::in(['bad', 'uncertain', 'good'])
            ],
            'review.comment' => 'nullable|string|max:65535',
            'review.spring_id' => 'required|integer',
        ];
    }

    public function mount($spring)
    {
        $this->spring = $spring;
        $this->review = new Review();
        $this->review->spring_id = $this->spring->id;
        $this->review->visited_at = now()->format('Y-m-d');
    }

    public function render()
    {
        $photos = Photo::whereIn('id', $this->photosIds)->orderByDesc('id')->get();

        return view('livewire.reviews.create', ['photos' => $photos]);
    }

    public function store()
    {
        $this->validate();

        if (Auth::check()) {
            $this->review->user_id = Auth::user()->id;
        }
        $this->review->save();

        $photos = Photo::whereIn('id', $this->photosIds)->orderByDesc('id')->get();

        foreach ($photos as $photo) {
            $photo->review_id = $this->review->id;
            $photo->save();
        }

        return redirect()->route('index');
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

        $exif = new Exif($this->file);

        $photo->latitude = $exif->latitude();
        $photo->longitude = $exif->longitude();

        $photo->save();

        $this->file->storeAs('/', $photo->filename, 'photos');

        $this->photosIds[] = $photo->id;
    }
}
