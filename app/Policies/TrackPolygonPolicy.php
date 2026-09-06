<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

final class TrackPolygonPolicy
{
    public function create(?User $user): bool
    {
        return true;
    }
}
