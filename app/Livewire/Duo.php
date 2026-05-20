<?php

namespace App\Livewire;

use App\Models\Spring;
use Livewire\Component;
use Livewire\Attributes\Url;

class Duo extends Component
{
    #[Url(history: true)]
    public $page = [];

    public $firstRender;

    public function mount()
    {
        $this->page = array_merge(config('duo.url_defaults'), $this->page);
        $this->firstRender = true;

        if ($redirect = $this->resolveSpringRedirect()) {
            return $redirect;
        }
    }

    public function updatedPage()
    {
        // prevents unexisting array keys when the back button is used
        $this->page = array_merge(config('duo.url_defaults'), $this->page);
    }

    public function render()
    {
        $coordinates = [];

        if ($this->firstRender && $this->page['spring'] > 0) {
            $spring = Spring::find($this->page['spring']);

            if (! $spring) abort(404);

            $coordinates = [
                floatval($spring->longitude),
                floatval($spring->latitude)
            ];
        }

        return view('livewire.duo', compact('coordinates'));
    }

    // Springs marked as duplicates redirect to their canonical target on
    // initial page load. Pass ?redirect=false to bypass (for admins viewing
    // the merged-away source).
    protected function resolveSpringRedirect()
    {
        if (request()->query('redirect') === 'false') {
            return null;
        }

        $springId = $this->page['spring'] ?? null;
        if (! $springId) {
            return null;
        }

        $spring = Spring::find($springId);
        if (! $spring) {
            return null;
        }

        $target = $spring->finallyRedirectedTo();
        if (! $target) {
            return null;
        }

        return $this->redirect(duo_route(['spring' => $target->id]));
    }
}
