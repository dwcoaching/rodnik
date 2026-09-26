<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Map;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class DeleteMapAction
{
    public function __invoke(?User $user, Map $map): void
    {
        $this->authorize($user, $map);
        $this->validate($map);
        $this->execute($user, $map);
    }

    public function authorize(?User $user, Map $map): void
    {
        Gate::forUser($user)->authorize('delete', $map);
    }

    public function validate(Map $map): void
    {
        abort_unless($map->exists, 404);
    }

    public function execute(?User $user, Map $map): void
    {
        DB::transaction(function () use ($user, $map): void {
            $current = Map::query()->whereKey($map->id)->where('user_id', $user?->id)->lockForUpdate()->firstOrFail();
            $current->delete();
        }, attempts: 3);
    }
}
