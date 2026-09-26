<?php

declare(strict_types=1);

namespace App\Livewire\Springs;

use App\Actions\Springs\PatchSpringsAction;
use App\Models\Spring;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

final class Create extends Component
{
    use AuthorizesRequests;

    public $springId;

    public $name;

    public $type;

    public $spring;

    public $saving = false;

    public function mount($springId)
    {
        $this->springId = $springId;
        $this->spring = Spring::find($this->springId);
        $this->authorize('update', $this->spring);

        $this->type = $this->spring->type;
        $this->name = $this->spring->name;
    }

    public function render()
    {
        $this->saving = false;

        return view('livewire.springs.create', [
            'waterSourceTypes' => Spring::TYPES,
        ]);
    }

    public function store(PatchSpringsAction $patchSprings)
    {
        $patchSprings($this->spring, [
            'type' => $this->type,
            'name' => $this->name,
        ]);

        return $this->redirect(duo_route(['spring' => $this->springId]), navigate: true);
    }
}
