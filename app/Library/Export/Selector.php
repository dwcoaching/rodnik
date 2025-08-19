<?php

namespace App\Library\Export;

use App\Models\User;
use App\Models\Spring;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class Selector
{
    public ?User $user = null;

    public function __construct() {}

    public function forUser(?User $user = null): static
    {
        $this->user = $user;
        return $this;
    }

    public function getQuery(): Builder
    {
        if ($this->user) {
            return $this->user->springs()
                ->select('springs.id')->getQuery();
        } else {
            return Spring::whereHas('reports')
                ->orWhereHas('springRevisions', function (Builder $query) {
                    $query->where('revision_type', 'user');
                })
                ->select('id');
        }
    }
}