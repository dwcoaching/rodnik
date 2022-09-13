<?php

namespace App\Models;

use App\Models\Spring;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SpringRevision extends Model
{
    use HasFactory;

    public function spring()
    {
        return $this->belongsTo(Spring::class);
    }

    public function apply()
    {
        $this->spring->latitude = $this->latitude;
        $this->spring->longitude = $this->longitude;
        $this->spring->name = $this->name;
        $this->spring->type = $this->type;
        $this->spring->seasonal = $this->seasonal;

        $this->current = true;
        $this->save();

        $this->spring->save();
        $this->spring->invalidateTiles();
    }
}
