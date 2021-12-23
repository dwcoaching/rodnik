<?php

namespace App\Http\Livewire\Springs;

use App\Library\Exif;
use App\Models\Photo;
use Livewire\Component;
use Livewire\WithFileUploads;
use Intervention\Image\Facades\Image;

class Create extends Component
{
    use WithFileUploads;

    public $file;
    public $photosIds = [];

    public function render()
    {
        $photos = Photo::whereIn('id', $this->photosIds)->get();
        return view('livewire.springs.create', ['photos' => $photos]);
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
