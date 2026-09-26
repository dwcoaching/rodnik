<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Map;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Map> */
final class MapFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'slug' => Str::lower(Str::random(8)),
            'user_id' => null,
            'title' => fake()->sentence(3),
            'track_id' => null,
            'is_starred' => false,
            'state' => [
                'version' => 1,
                'center' => [37.6173, 55.7558],
                'zoom' => 12,
                'sourceName' => 'osm',
                'filters' => [
                    'spring' => true,
                    'water_well' => true,
                    'water_tap' => true,
                    'drinking_water' => true,
                    'fountain' => true,
                    'other' => true,
                    'with_reports' => false,
                    'along' => false,
                ],
                'overlays' => ['stravaPublic' => false, 'osmTraces' => false],
                'page' => ['spring' => null, 'user' => null, 'location' => null],
                'fullscreen' => false,
                'minimized' => false,
            ],
        ];
    }
}
