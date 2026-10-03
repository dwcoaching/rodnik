<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Spring;
use App\Services\Geocoding\GeocodeSpring;
use Throwable;

final class SpringObserver
{
    public function saving(Spring $spring): void
    {
        if ($spring->exists && ! $this->coordinatesChanged($spring)) {
            return;
        }

        try {
            app(GeocodeSpring::class)->apply($spring);
        } catch (Throwable $exception) {
            // Adding/moving a source must still work if the dataset is unavailable.
            // Old labels describe old coordinates and must never survive a move.
            $spring->forceFill(array_fill_keys(GeocodeSpring::FIELDS, null));
            report($exception);
        }
    }

    private function coordinatesChanged(Spring $spring): bool
    {
        foreach (['latitude', 'longitude'] as $coordinate) {
            $before = $spring->getOriginal($coordinate);
            $after = $spring->{$coordinate};

            if (($before === null) !== ($after === null) || (float) $before !== (float) $after) {
                return true;
            }
        }

        return false;
    }
}
