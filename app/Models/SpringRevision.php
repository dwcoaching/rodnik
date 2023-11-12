<?php

namespace App\Models;

use App\Models\User;
use App\Models\Spring;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SpringRevision extends Model
{
    use HasFactory;

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function spring()
    {
        return $this->belongsTo(Spring::class);
    }
}
