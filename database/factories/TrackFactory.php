<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Track;
use App\Support\TrackGeometry;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Track>
 */
final class TrackFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $longitude = fake()->randomFloat(5, -179, 179);
        $latitude = fake()->randomFloat(5, -89, 89);
        $track = [
            'type' => 'FeatureCollection',
            'features' => [[
                'type' => 'Feature',
                'properties' => (object) ['name' => fake()->sentence(3)],
                'geometry' => [
                    'type' => 'LineString',
                    'coordinates' => [[$longitude, $latitude], [$longitude + 0.1, $latitude + 0.1]],
                ],
            ]],
        ];
        $geometry = json_decode(json_encode($track, JSON_THROW_ON_ERROR), false, 32, JSON_THROW_ON_ERROR);

        return [
            'token' => Str::random(10),
            'name' => TrackGeometry::name($geometry),
            'hash' => hash('sha256', json_encode($track, JSON_THROW_ON_ERROR)),
            'track' => $track,
            'summary' => TrackGeometry::summary($geometry),
            'user_id' => null,
        ];
    }
}
