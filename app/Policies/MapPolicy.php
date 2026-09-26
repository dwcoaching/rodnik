<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Map;
use App\Models\User;

final class MapPolicy
{
    public function create(?User $user): bool
    {
        return $user !== null;
    }

    public function update(?User $user, Map $map): bool
    {
        return $user !== null && $map->user_id === $user->id;
    }

    public function delete(?User $user, Map $map): bool
    {
        return $this->update($user, $map);
    }
}
