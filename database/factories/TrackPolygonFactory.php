<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\TrackPolygon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrackPolygon>
 */
final class TrackPolygonFactory extends Factory
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
        $polygon = [
            'type' => 'Polygon',
            'coordinates' => [[
                [$longitude, $latitude],
                [$longitude + 0.1, $latitude],
                [$longitude + 0.1, $latitude + 0.1],
                [$longitude, $latitude],
            ]],
        ];

        return [
            'hash' => hash('sha256', json_encode($polygon, JSON_THROW_ON_ERROR)),
            'polygon' => $polygon,
            'user_id' => null,
            'latitude_from' => $latitude,
            'latitude_to' => $latitude + 0.1,
            'longitude_from' => $longitude,
            'longitude_to' => $longitude + 0.1,
        ];
    }
}
