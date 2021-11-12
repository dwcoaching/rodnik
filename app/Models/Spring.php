<?php

namespace App\Models;

use App\Models\Update;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Spring extends Model
{
    use HasFactory;

    public function updates()
    {
        return $this->hasMany(Update::class);
    }
}
