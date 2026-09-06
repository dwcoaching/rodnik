<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\TrackPolygon;
use App\Models\User;
use App\Rules\GeoJsonPolygonRule;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RoundingMode;

final class StoreTrackPolygonAction
{
    /** @param array<string, mixed> $attributes */
    public function __invoke(?User $user, array $attributes): TrackPolygon
    {
        $this->authorize($user);
        $validated = $this->validate($attributes);

        return $this->execute($user, $validated);
    }

    public function authorize(?User $user): void
    {
        Gate::forUser($user)->authorize('create', TrackPolygon::class);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{hash: string, polygon: string}
     */
    public function validate(array $attributes): array
    {
        $validated = Validator::make($attributes, [
            'hash' => ['bail', 'required', 'string', 'regex:/\A[a-f0-9]{64}\z/'],
            'polygon' => ['bail', 'required', 'string', new GeoJsonPolygonRule],
        ])->validate();

        if (! hash_equals($validated['hash'], hash('sha256', $validated['polygon']))) {
            throw ValidationException::withMessages([
                'hash' => ['The hash does not match the uploaded polygon.'],
            ]);
        }

        return $validated;
    }

    /** @param array{hash: string, polygon: string} $validated */
    public function execute(?User $user, array $validated): TrackPolygon
    {
        $geometry = json_decode($validated['polygon'], true, 32, JSON_THROW_ON_ERROR);
        $polygons = $geometry['type'] === 'Polygon' ? [$geometry['coordinates']] : $geometry['coordinates'];
        $latitudeFrom = 90;
        $latitudeTo = -90;
        $longitudeFrom = 180;
        $longitudeTo = -180;

        foreach ($polygons as $polygon) {
            foreach ($polygon as $ring) {
                foreach ($ring as [$longitude, $latitude]) {
                    $latitudeFrom = min($latitudeFrom, $latitude);
                    $latitudeTo = max($latitudeTo, $latitude);
                    $longitudeFrom = min($longitudeFrom, $longitude);
                    $longitudeTo = max($longitudeTo, $longitude);
                }
            }
        }

        return TrackPolygon::query()->firstOrCreate(['hash' => $validated['hash']], [
            'polygon' => $geometry,
            'user_id' => $user?->id,
            'latitude_from' => round($latitudeFrom, 7, RoundingMode::NegativeInfinity),
            'latitude_to' => round($latitudeTo, 7, RoundingMode::PositiveInfinity),
            'longitude_from' => round($longitudeFrom, 7, RoundingMode::NegativeInfinity),
            'longitude_to' => round($longitudeTo, 7, RoundingMode::PositiveInfinity),
        ]);
    }
}
