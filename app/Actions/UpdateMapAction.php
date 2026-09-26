<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Map;
use App\Models\User;
use App\Support\MapPayload;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class UpdateMapAction
{
    public function __construct(private MapPayload $payload) {}

    /** @param array<string, mixed> $attributes */
    public function __invoke(?User $user, Map $map, array $attributes, ?string $locale = null): Map
    {
        $this->authorize($user, $map);
        $validated = $this->validate($attributes, $locale);

        return $this->execute($user, $map, $validated);
    }

    public function authorize(?User $user, Map $map): void
    {
        Gate::forUser($user)->authorize('update', $map);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{title: string, state: array<string, mixed>, track_token: ?string, version: int}
     */
    public function validate(array $attributes, ?string $locale = null): array
    {
        return $this->payload->validate($attributes, updating: true, locale: $locale);
    }

    /** @param array{title: string, state: array<string, mixed>, track_token: ?string, version: int} $validated */
    public function execute(?User $user, Map $map, array $validated): Map
    {
        return DB::transaction(function () use ($user, $map, $validated): Map {
            $current = Map::query()->whereKey($map->id)->where('user_id', $user?->id)->lockForUpdate()->first();
            abort_if($current === null || $current->version !== $validated['version'], 409, __('This map has changed. Reload it before saving your changes.'));
            $track = $this->payload->resolveTrack($validated);
            $changes = new Map([
                'state' => $validated['state'],
                ...$track,
            ]);
            $updates = [
                'state' => $changes->getAttributes()['state'],
                ...$track,
                'version' => DB::raw('version + 1'),
            ];
            if (array_key_exists('title', $validated)) {
                $updates['title'] = $validated['title'];
            }
            $updated = Map::query()
                ->whereKey($map->id)
                ->where('user_id', $user?->id)
                ->where('version', $validated['version'])
                ->update($updates);

            abort_if($updated !== 1, 409, __('This map has changed. Reload it before saving your changes.'));

            return $map->refresh();
        }, attempts: 3);
    }
}
