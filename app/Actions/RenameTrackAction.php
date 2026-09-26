<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Track;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

final class RenameTrackAction
{
    /** @param array<string, mixed> $attributes */
    public function __invoke(?User $user, Track $track, array $attributes): Track
    {
        $this->authorize($user, $track);
        $validated = $this->validate($attributes);

        return $this->execute($track, $validated);
    }

    public function authorize(?User $user, Track $track): void
    {
        Gate::forUser($user)->authorize('update', $track);
    }

    /** @param array<string, mixed> $attributes
     * @return array{name: string}
     */
    public function validate(array $attributes): array
    {
        if (isset($attributes['name']) && is_string($attributes['name'])) {
            $attributes['name'] = mb_trim($attributes['name']);
        }

        return Validator::make($attributes, ['name' => ['required', 'string', 'max:160']])->validate();
    }

    /** @param array{name: string} $validated */
    public function execute(Track $track, array $validated): Track
    {
        $track->update($validated);

        return $track;
    }
}
