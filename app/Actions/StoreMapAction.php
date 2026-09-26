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
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class StoreMapAction
{
    public function __construct(private MapPayload $payload, private MapDetailsPayload $details) {}

    /** @param array<string, mixed> $attributes */
    public function __invoke(?User $user, array $attributes, ?string $locale = null): Map
    {
        $this->authorize($user);
        $validated = $this->validate($attributes, $locale);

        return $this->execute($user, $validated, $locale);
    }

    public function authorize(?User $user): void
    {
        Gate::forUser($user)->authorize('create', Map::class);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{title: string, state: array<string, mixed>, track_token: ?string}
     */
    public function validate(array $attributes, ?string $locale = null): array
    {
        $details = $this->details->validate($attributes, locale: $locale);

        return array_merge($this->payload->validate(array_merge($attributes, $details)), $details);
    }

    /** @param array{title: string, state: array<string, mixed>, track_token: ?string} $validated */
    public function execute(?User $user, array $validated, ?string $locale = null): Map
    {
        $attempts = 0;

        do {
            try {
                return DB::transaction(function () use ($user, $validated): Map {
                    $track = $this->payload->resolveTrack($validated);
                    $map = new Map([...$validated, ...$track]);
                    $map->slug = $validated['slug'] ?? Str::lower(Str::random(8));
                    $map->user_id = $user?->id;
                    $map->save();

                    return $map;
                }, attempts: 3);
            } catch (UniqueConstraintViolationException $exception) {
                if (! empty($validated['slug']) && Map::query()->where('slug', $validated['slug'])->exists()) {
                    throw ValidationException::withMessages(['slug' => [__('ui.maps.link_unavailable', locale: $locale)]]);
                }
                if (++$attempts >= 5) {
                    throw $exception;
                }
            }
        } while (true);
    }
}
