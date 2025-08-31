<?php

namespace App\Livewire\Profile;

use App\Models\User;
use Livewire\Component;
use Livewire\Attributes\Locked;
use App\Library\Export\Selector;
use App\Library\Export\CsvWriter;
use App\Library\Export\JsonWriter;
use App\Library\Export\XlsxWriter;
use Illuminate\Support\Facades\Storage;

class ExportPersonalContributions extends Component
{
    #[Locked]
    public User $user;
    public function mount()
    {
        $this->user = auth()->user();
    }

    public function render()
    {
        return view('livewire.profile.export-personal-contributions');
    }

    public function exportJson()
    {
        $selector = new Selector();
        $query = $selector->forUser($this->user)->getQuery();
        
        $filename = (new JsonWriter($query))->forUser($this->user)->save();

        return response()->download(Storage::disk('public')->path('exports/users/' . $filename));

    }

    public function exportCsv() 
    {
        $selector = new Selector();
        $query = $selector->forUser($this->user)->getQuery();
        
        $filename = (new CsvWriter($query))->forUser($this->user)->save();

        return response()->download(Storage::disk('public')->path('exports/users/' . $filename));
    }

    public function exportXlsx()
    {
        $selector = new Selector();
        $query = $selector->forUser($this->user)->getQuery();
        
        $filename = (new XlsxWriter($query))->forUser($this->user)->save();

        return response()->download(Storage::disk('public')->path('exports/users/' . $filename));
    }
}
