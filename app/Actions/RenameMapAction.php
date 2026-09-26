<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Map;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

final class RenameMapAction
{
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
     * @return array{title: string, version: int}
     */
    public function validate(array $attributes, ?string $locale = null): array
    {
        if (isset($attributes['title']) && is_string($attributes['title'])) {
            $attributes['title'] = mb_trim($attributes['title']);
        }

        return Validator::make($attributes, [
            'title' => ['bail', 'required', 'string', 'max:160'],
            'version' => ['required', 'integer:strict', 'between:1,4294967294'],
        ], ['title.required' => __('ui.maps.title_required', locale: $locale)])->validate();
    }

    /** @param array{title: string, version: int} $validated */
    public function execute(?User $user, Map $map, array $validated): Map
    {
        return DB::transaction(function () use ($user, $map, $validated): Map {
            $updated = Map::query()
                ->whereKey($map->id)
                ->where('user_id', $user?->id)
                ->where('version', $validated['version'])
                ->update([
                    'title' => $validated['title'],
                    'version' => DB::raw('version + 1'),
                ]);

            abort_if($updated !== 1, 409, __('This map has changed. Reload it before saving your changes.'));

            return $map->refresh();
        });
    }
}
