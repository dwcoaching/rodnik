<?php

namespace Database\Factories;

use App\Models\Spring;
use Illuminate\Database\Eloquent\Factories\Factory;

class SpringFactory extends Factory
{
    protected $model = Spring::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'type' => $this->faker->randomElement(Spring::TYPES),
            // Web Mercator tiles stop at ±85.05°; beyond that tile invalidation aborts with 404.
            'latitude' => $this->faker->latitude(-85, 85),
            'longitude' => $this->faker->longitude,
            'intermittent' => $this->faker->randomElement([null, 'yes', 'no']),
            'hidden_at' => null,
        ];
    }
} 