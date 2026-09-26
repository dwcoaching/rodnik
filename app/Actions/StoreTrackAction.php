<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Track;
use App\Models\User;
use App\Rules\GeoJsonTrackRule;
use App\Support\TrackGeometry;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class StoreTrackAction
{
    /** @param array<string, mixed> $attributes */
    public function __invoke(?User $user, array $attributes): Track
    {
        $this->authorize($user);
        $validated = $this->validate($attributes);

        return $this->execute($user, $validated);
    }

    public function authorize(?User $user): void
    {
        Gate::forUser($user)->authorize('create', Track::class);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{hash: string, track: string, name?: ?string}
     */
    public function validate(array $attributes): array
    {
        $validated = Validator::make($attributes, [
            'hash' => ['bail', 'required', 'string', 'regex:/\A[a-f0-9]{64}\z/'],
            'track' => ['bail', 'required', 'string', new GeoJsonTrackRule],
            'name' => ['sometimes', 'nullable', 'string', 'max:160'],
        ])->validate();

        if (! hash_equals($validated['hash'], hash('sha256', $validated['track']))) {
            throw ValidationException::withMessages([
                'hash' => ['The hash does not match the uploaded track.'],
            ]);
        }

        return $validated;
    }

    /** @param array{hash: string, track: string, name?: ?string} $validated */
    public function execute(?User $user, array $validated): Track
    {
        $attempts = 0;
        do {
            try {
                return DB::transaction(function () use ($user, $validated): Track {
                    if ($user !== null) {
                        $existing = Track::query()->where('hash', $validated['hash'])->whereBelongsTo($user)->lockForUpdate()->first();
                        if ($existing !== null) {
                            return $existing;
                        }
                    }
                    $geometry = json_decode($validated['track'], false, 32, JSON_THROW_ON_ERROR);

                    return Track::query()->create([
                        'token' => Str::random(10),
                        'hash' => $validated['hash'],
                        'track' => $geometry,
                        'user_id' => $user?->id,
                        'name' => mb_trim($validated['name'] ?? '') ?: TrackGeometry::name($geometry),
                        'summary' => TrackGeometry::summary($geometry),
                    ]);
                }, attempts: 3);
            } catch (UniqueConstraintViolationException $exception) {
                if (++$attempts >= 5) {
                    throw $exception;
                }
            }
        } while (true);
    }
}
