<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Map;
use App\Models\Track;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class DeleteTrackAction
{
    public function __invoke(?User $user, Track $track): void
    {
        $this->authorize($user, $track);
        $this->validate($track);
        $this->execute($track);
    }

    public function authorize(?User $user, Track $track): void
    {
        Gate::forUser($user)->authorize('delete', $track);
    }

    public function validate(Track $track): void
    {
        abort_unless($track->exists, 404);
    }

    public function execute(Track $track): void
    {
        DB::transaction(function () use ($track): void {
            $current = Track::query()->whereKey($track->id)->lockForUpdate()->firstOrFail(['id', 'token']);
            $maps = Map::query()->where('track_id', $current->id)->select(['id', 'state', 'version'])
                ->lockForUpdate()->lazyById(100);
            foreach ($maps as $map) {
                $state = $map->state;
                $state['filters']['along'] = false;
                $map->fill(['track_id' => null, 'state' => $state]);
                $map->version++;
                $map->save();
            }
            $current->delete();
        }, attempts: 3);
    }
}
