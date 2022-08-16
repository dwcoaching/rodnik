<?php

namespace App\Http\Livewire;

use App\Models\Review;
use Livewire\Component;
use App\Models\Spring as SpringModel;

class Spring extends Component
{
    public $springId;

    public function setSpring($springId)
    {
        $this->springId = $springId;
    }

    public function unselectSpring()
    {
        $this->springId = null;
    }

    public function render()
    {
        if ($this->springId) {
            if (! $spring = SpringModel::find($this->springId)) {
                abort(404);
            }

            $reviews = $spring->reviews()->orderByDesc('created_at')->get();
            $coordinates = [
                floatval($spring->longitude),
                floatval($spring->latitude)
            ];
        } else
        {
            $spring = null;
            $reviews = [];
            $coordinates = [];
        }

        return view('livewire.spring', compact('reviews', 'spring', 'coordinates'));
    }
}
