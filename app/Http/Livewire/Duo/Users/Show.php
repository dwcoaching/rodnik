<?php

namespace App\Http\Livewire\Duo\Users;

use App\Models\User;
use Livewire\Component;

class Show extends Component
{
    public $userId;
    public $limit = 12;

    public function setUser($userId)
    {
        $this->userId = $userId;
    }

    public function render()
    {
        if (! $this->userId) {
            $lastReports = [];
            $user = null;
        } else {
            if (! $user = User::find($this->userId)) {
                abort(404);
            }

            $lastReports = $user->reports()
                ->whereNull('hidden_at')
                ->with(['user', 'photos', 'spring'])
                ->latest()
                ->limit($this->limit)
                ->get();
        }

        return view('livewire.duo.users.show', compact('user', 'lastReports'));
    }
}
