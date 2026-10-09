<?php

declare(strict_types=1);

namespace App\Services\Geocoding;

use App\Contracts\ReverseGeocoder;
use App\Contracts\SnapshotReverseGeocoder;
use App\Models\Spring;
use Closure;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class GeocodeSpring
{
    public const FIELDS = [
        'country_id', 'region_id', 'nearest_city_id', 'nearest_city_distance_km',
        'geocoding_source', 'geocoded_at',
    ];

    public function __construct(private readonly ReverseGeocoder $geocoder) {}

    public function validateSource(): void
    {
        $this->geocoder->validateSource();
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function withSnapshot(callable $callback): mixed
    {
        return $this->geocoder instanceof SnapshotReverseGeocoder
            ? $this->geocoder->withSnapshot($callback)
            : $callback();
    }

    /** Resolve first, so a failed manual lookup preserves the previous result. */
    public function apply(Spring $spring): bool
    {
        $location = null;

        if ($this->hasValidCoordinates($spring)) {
            $location = $this->geocoder->reverse((float) $spring->latitude, (float) $spring->longitude);
        }

        $spring->forceFill(array_fill_keys(self::FIELDS, null));

        if ($location !== null) {
            $spring->forceFill($location->attributes());
        }

        $spring->geocoded_at = $this->hasValidCoordinates($spring) ? now() : null;

        return $location !== null;
    }

    /** Update only derived fields, and never overwrite a concurrent coordinate edit. */
    public function geocode(Spring $spring, ?Closure $persistGuard = null): bool
    {
        $matched = $this->apply($spring);
        $attributes = array_intersect_key($spring->getAttributes(), array_flip(self::FIELDS));
        $query = Spring::query()->whereKey($spring->getKey());

        foreach (['latitude', 'longitude'] as $coordinate) {
            $spring->{$coordinate} === null
                ? $query->whereNull($coordinate)
                : $query->where($coordinate, $spring->{$coordinate});
        }

        DB::transaction(function () use ($query, $attributes, $persistGuard): void {
            // Bulk workers lock their run here, after the potentially slow lookup.
            // Releasing that run and saving its derived fields cannot interleave.
            if ($persistGuard !== null && ! $persistGuard()) {
                throw new RuntimeException('The reverse geocoding run no longer belongs to this worker.');
            }

            // Query builder avoids save events and unrelated source metadata.
            if ($query->toBase()->update($attributes) === 0 && ! $query->exists()) {
                throw new RuntimeException('Water source coordinates changed during reverse geocoding. Retry the lookup.');
            }
        });

        $spring->syncOriginalAttributes(self::FIELDS);

        return $matched;
    }

    private function hasValidCoordinates(Spring $spring): bool
    {
        return is_numeric($spring->latitude) && is_numeric($spring->longitude)
            && is_finite((float) $spring->latitude) && is_finite((float) $spring->longitude)
            && (float) $spring->latitude >= -90 && (float) $spring->latitude <= 90
            && (float) $spring->longitude >= -180 && (float) $spring->longitude <= 180;
    }
}
