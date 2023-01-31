<?php

namespace App\Models;

use App\Models\User;
use App\Models\Photo;
use App\Models\Spring;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Report extends Model
{
    use HasFactory;

    protected $dates = [
        'visited_at',
        'created_at',
        'updated_at'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function photos()
    {
        return $this->hasMany(Photo::class);
    }

    public function spring()
    {
        return $this->belongsTo(Spring::class);
    }
}
