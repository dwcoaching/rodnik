<?php

namespace App\Models;

use App\Models\User;
use App\Models\Photo;
use App\Models\Spring;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Report extends Model
{
    use HasFactory;

    protected $casts = [
        'visited_at' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
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

    public function getShortCommentAttribute()
    {
        return mb_strlen($this->comment) > 150
            ? trim(mb_substr($this->comment, 0, 150)) . '...'
            : $this->comment;
    }

    public function scopeVisible(Builder $query): void
    {
        $query->whereNull('reports.from_osm')
            ->whereNull('reports.hidden_at');
    }
}
