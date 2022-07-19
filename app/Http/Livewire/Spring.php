<?php

namespace App\Http\Livewire;

use App\Models\Review;
use Livewire\Component;
use App\Models\Spring as SpringModel;

class Spring extends Component
{
    public $spring;
    public $review;
    public $showNewComment;

    protected $rules = [
        'review.comment' => 'required|string|min:1'
    ];

    public function mount()
    {
        $this->review = new Review();
    }

    public function setSpring($springId)
    {
        $spring = SpringModel::find($springId);

        if ($spring) {
            $this->spring = $spring;
        }

        $this->showNewComment = false;
    }

    public function render()
    {
        if ($this->spring) {
            $reviews = $this->spring->reviews()->orderByDesc('created_at')->get();
        } else
        {
            $reviews = [];
        }

        return view('livewire.spring', compact('reviews'));
    }

    public function storeReview()
    {
        $this->validate();
        $this->review->spring_id = $this->spring->id;
        $this->review->save();

        $this->review = new Review();
        $this->showNewComment = false;
    }
}
