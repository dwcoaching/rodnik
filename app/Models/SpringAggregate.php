<?php

namespace App\Models;

use App\Models\Spring;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SpringAggregate extends Model
{
    use HasFactory;

    public function calculate()
    {
        $springCount = Spring::where('latitude', '>', $this->latitude - $this->step / 2)
            ->where('latitude', '<', $this->latitude + $this->step / 2)
            ->where('longitude', '>', $this->longitude - $this->step / 2)
            ->where('longitude', '<', $this->longitude + $this->step / 2)
            ->count();

        $this->count = $springCount;
        $this->save();
    }
}
