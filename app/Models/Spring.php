<?php

namespace App\Models;

use App\Models\OSMTag;
use App\Models\Review;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Spring extends Model
{
    use HasFactory;

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function osm_tags()
    {
        return $this->hasMany(OSMTag::class);
    }
}
