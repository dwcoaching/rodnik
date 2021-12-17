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

    public $files;
    public $photosIds = [];

    public function render()
    {
        $photos = Photo::whereIn('id', $this->photosIds)->get();
        return view('livewire.springs.create', ['photos' => $photos]);
    }

    public function updatedFiles()
    {
        $this->validate([
            'files.*' => 'image|max:10240', // 10MB Max
        ]);

        foreach ($this->files as $file) {
            $photo = new Photo();
            $photo->original_extension = $file->getClientOriginalExtension();
            $photo->original_filename = $file->getClientOriginalName();
            $photo->extension = $file->extension();

            $exif = new Exif($file);

            $photo->latitude = $exif->latitude();
            $photo->longitude = $exif->longitude();

            $photo->save();

            $file->storeAs('/', $photo->filename, 'photos');

            $this->photosIds[] = $photo->id;
        }
    }
}
