<?php

namespace App\Http\Livewire;

use App\Models\Update;
use Livewire\Component;
use App\Models\Spring as SpringModel;

class Spring extends Component
{
    public $spring;
    public $update;
    public $showNewComment;

    protected $rules = [
        'update.comment' => 'required|string|min:1'
    ];

    public function mount()
    {
        $this->update = new Update();
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
            $updates = $this->spring->updates()->orderByDesc('created_at')->get();
        } else
        {
            $updates = [];
        }

        return view('livewire.spring', compact('updates'));
    }

    public function storeUpdate()
    {
        $this->validate();
        $this->update->spring_id = $this->spring->id;
        $this->update->save();

        $this->update = new Update();
        $this->showNewComment = false;
    }
}
