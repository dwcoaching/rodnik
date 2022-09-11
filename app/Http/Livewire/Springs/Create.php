<?php

namespace App\Http\Livewire\Springs;

use App\Models\Spring;
use Livewire\Component;
use App\Models\SpringRevision;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class Create extends Component
{
    public $spring;
    public $springRevision;

    protected function rules()
    {
        return [
            'springRevision.name' => 'nullable',
            'springRevision.type' => ['nullable', Rule::in(['Родник', 'Колодец', 'Кран'])],
            'springRevision.coordinates' => 'nullable',
        ];
    }

    public function mount(Spring $spring)
    {
        $this->spring = $spring ? $spring : new Spring();
        $this->springRevision = new SpringRevision();
    }

    public function render()
    {
        return view('livewire.springs.create');
    }

    public function store()
    {
        $coordinates = explode(',', $this->springRevision->coordinates);
        unset($this->springRevision->coordinates);

        $this->springRevision->latitude = $coordinates[0];
        $this->springRevision->longitude = $coordinates[1];

        $this->user_id = Auth::check() ? Auth::user()->id : null;
        $this->springRevision->save();

        $this->spring = new Spring();
        $this->spring->save();

        $this->springRevision->spring_id = $this->spring->id;
        $this->springRevision->save();

        $this->spring->applyRevision($this->springRevision);

        return redirect()->route('show', $this->spring);
    }
}
