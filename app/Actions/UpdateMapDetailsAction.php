<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Map;
use App\Models\User;
use App\Support\MapDetailsPayload;
use App\Support\MapPayload;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class UpdateMapDetailsAction
{
    public function __construct(private MapDetailsPayload $payload, private MapPayload $mapPayload) {}

    /** @param array<string, mixed> $attributes */
    public function __invoke(?User $user, Map $map, array $attributes, ?string $locale = null): Map
    {
        $this->authorize($user, $map);
        $validated = $this->validate($map, $attributes, $locale);

        return $this->execute($user, $map, $validated, $locale);
    }

    public function authorize(?User $user, Map $map): void
    {
        Gate::forUser($user)->authorize('update', $map);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{title?: string, slug?: ?string, version: int, state?: array<string, mixed>, track_token?: ?string}
     */
    public function validate(Map $map, array $attributes, ?string $locale = null): array
    {
        $validated = $this->payload->validate($attributes, $map, $locale);

        if (array_key_exists('state', $attributes) || array_key_exists('track_token', $attributes)) {
            $validated = array_merge($this->mapPayload->validate(array_merge($attributes, $validated), updating: true), $validated);
        }

        return $validated;
    }

    /** @param array{title?: string, slug?: ?string, version: int, state?: array<string, mixed>, track_token?: ?string} $validated */
    public function execute(?User $user, Map $map, array $validated, ?string $locale = null): Map
    {
        try {
            return DB::transaction(function () use ($user, $map, $validated): Map {
                $current = Map::query()->whereKey($map->id)->where('user_id', $user?->id)->lockForUpdate()->first();
                abort_if($current === null || $current->version !== $validated['version'], 409, __('This map has changed. Reload it before saving your changes.'));

                $current->slug = $validated['slug'] ?? $current->slug;
                if (array_key_exists('title', $validated)) {
                    $current->title = $validated['title'];
                }
                if (array_key_exists('state', $validated)) {
                    $current->fill($this->mapPayload->resolveTrack($validated));
                    $current->state = $validated['state'];
                }
                $current->version++;
                $current->save();

                return $current;
            }, attempts: 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['slug' => [__('ui.maps.link_unavailable', locale: $locale)]]);
        }
    }
}
