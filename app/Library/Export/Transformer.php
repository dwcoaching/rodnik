<?php

namespace App\Library\Export;

use App\Models\User;
use App\Models\Photo;
use App\Models\Report;
use App\Models\Spring;
use App\Models\SpringRevision;
use Illuminate\Database\Eloquent\Collection;

class Transformer
{
    public ?User $user = null;

    public Collection $springs;

    public function __construct(Collection $springs)
    {
        $this->springs = $this->load($springs);
    }

    public function forUser(?User $user = null): static
    {
        $this->user = $user;
        return $this;
    }

    public function load(Collection $springs): Collection
    {
        $springs = Spring::select('id', 'type', 'name', 'latitude', 'longitude')
            ->whereIn('id', $springs->pluck('id'))
            ->with([
                'reports' => function ($query) {
                    $query->select('id', 'spring_id', 'user_id', 'created_at', 'visited_at', 'state', 'quality', 'comment');
                },
                'reports.user' => function ($query) {
                    $query->select('id', 'name');
                },
                'reports.photos' => function ($query) {
                    $query->select('id', 'report_id', 'extension');
                },
                'springRevisions' => function ($query) {
                    $query->select('id', 'spring_id', 'user_id', 'new_latitude', 'new_longitude', 'new_type', 'new_name', 'created_at')
                        ->where('revision_type', 'user');
                },
                'springRevisions.user' => function ($query) {
                    $query->select('id', 'name');
                }
            ])
            ->get();

        return $springs;
    }
}