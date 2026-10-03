<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Spring;
use App\Services\Geocoding\GeocodeSpring;
use Illuminate\Support\Facades\Gate;

final class GeocodeSpringAction
{
    public function __construct(private readonly GeocodeSpring $geocoder) {}

    public function __invoke(Spring $spring): bool
    {
        $this->authorize();
        $this->validate();

        return $this->execute($spring);
    }

    private function authorize(): void
    {
        Gate::authorize('admin');
    }

    private function validate(): void
    {
        $this->geocoder->validateSource();
    }

    private function execute(Spring $spring): bool
    {
        return $this->geocoder->geocode($spring);
    }
}
