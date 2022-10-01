<?php

namespace App\Models;

use App\Models\Report;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Photo extends Model
{
    use HasFactory;

    public function report()
    {
        return $this->belongsTo(Report::class);
    }

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

    public function fullPath()
    {
        return Storage::disk('photos')->path($this->filename);
    }
}
