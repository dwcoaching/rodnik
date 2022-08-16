<?php

namespace App\Http\Livewire;

use App\Models\Review;
use Livewire\Component;
use App\Models\Spring as SpringModel;

class Spring extends Component
{
    public $springId;
    public $review;
    protected $initialRender;

    public function mount()
    {
        $this->review = new Review();
        $this->initialRender = true;
    }

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
            $spring = SpringModel::findOrFail($this->springId);
            $reviews = $spring->reviews()->orderByDesc('created_at')->get();
            $initialRender = $this->initialRender ? true : false;
            $coordinates = [
                floatval($spring->longitude),
                floatval($spring->latitude)
            ];
        } else
        {
            $spring = null;
            $reviews = [];
            $initialRender = false;
            $coordinates = [];
        }

        return view('livewire.spring', compact('reviews', 'spring', 'initialRender', 'coordinates'));
    }
}
