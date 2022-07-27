<?php

namespace App\Http\Livewire;

use App\Models\Review;
use Livewire\Component;
use App\Models\Spring as SpringModel;

class Spring extends Component
{
    public $spring_id;
    public $review;

    protected $queryString = ['spring_id'];

    public function mount()
    {
        $this->review = new Review();
    }

    public function setSpring($springId)
    {
        $this->spring_id = $springId;
    }

    public function render()
    {
        if ($this->spring_id) {

            $spring = SpringModel::findOrFail($this->spring_id);

            $reviews = $spring->reviews()->orderByDesc('created_at')->get();
        } else
        {
            $spring = null;

            $reviews = [];
        }

        return view('livewire.spring', compact('reviews', 'spring'));
    }
}
