<?php

namespace App\Livewire\Admin;

use App\Library\PossibleDuplicateSprings;
use Livewire\Attributes\Locked;
use Livewire\Component;

class PossibleDuplicatesTable extends Component
{
    #[Locked]
    public int $radius;

    #[Locked]
    public int $limit;

    public bool $loaded = false;

    public bool $timedOut = false;

    public bool $limitReached = false;

    public float $elapsedSeconds = 0.0;

    public array $duplicates = [];

    public function mount(int $radius, int $limit): void
    {
        $this->radius = PossibleDuplicateSprings::normalizeRadius($radius);
        $this->limit = PossibleDuplicateSprings::normalizeLimit($limit);
    }

    public function load(): void
    {
        $result = PossibleDuplicateSprings::scan(
            $this->radius,
            $this->limit,
            PossibleDuplicateSprings::TIME_LIMIT_SECONDS,
        );

        $this->duplicates = $result['duplicates']
            ->map(fn ($duplicate) => (array) $duplicate)
            ->all();
        $this->timedOut = $result['timed_out'];
        $this->limitReached = $result['limit_reached'];
        $this->elapsedSeconds = $result['elapsed_seconds'];
        $this->loaded = true;
    }

    public function render()
    {
        return view('livewire.admin.possible-duplicates-table');
    }
}
