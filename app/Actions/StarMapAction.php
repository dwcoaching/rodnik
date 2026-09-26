<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Map;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

final class StarMapAction
{
    /** @param array<string, mixed> $attributes */
    public function __invoke(?User $user, Map $map, array $attributes): Map
    {
        $this->authorize($user, $map);
        $validated = $this->validate($attributes);

        return $this->execute($user, $map, $validated);
    }

    public function authorize(?User $user, Map $map): void
    {
        Gate::forUser($user)->authorize('update', $map);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{starred: bool}
     */
    public function validate(array $attributes): array
    {
        return Validator::make($attributes, ['starred' => ['required', 'boolean:strict']])->validate();
    }

    /** @param array{starred: bool} $validated */
    public function execute(?User $user, Map $map, array $validated): Map
    {
        Map::query()->whereKey($map->id)->where('user_id', $user?->id)->toBase()
            ->update(['is_starred' => $validated['starred']]);

        return $map->refresh();
    }
}
