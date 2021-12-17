<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Photo extends Model
{
    use HasFactory;

    public function getUrlAttribute()
    {
        return Storage::disk('photos')->url($this->filename);
    }

    public function getFilenameAttribute()
    {
        return $this->id . '.' . $this->extension;
    }

    public function path()
    {
        return Storage::disk('photos')->url($this->filename);
    }
}
