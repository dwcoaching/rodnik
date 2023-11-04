<?php

namespace App\Livewire\Photos;

use App\Library\Exif;
use App\Models\Photo;
use Livewire\Component;
use Livewire\WithFileUploads;
use Intervention\Image\Facades\Image;

class Batch extends Component
{
    use WithFileUploads;

    public $file;
    public $photosIds = [];

    public function render()
    {
        $photos = Photo::whereIn('id', $this->photosIds)->orderByDesc('id')->get();
        return view('livewire.photos.batch', ['photos' => $photos]);
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
}
