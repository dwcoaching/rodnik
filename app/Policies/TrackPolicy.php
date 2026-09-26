<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Track;
use App\Models\User;

final class TrackPolicy
{
    public function create(?User $user): bool
    {
        return true;
    }

    public function update(User $user, Track $track): bool
    {
        return $track->user_id === $user->id;
    }

    public function delete(User $user, Track $track): bool
    {
        return $this->update($user, $track);
    }
}
