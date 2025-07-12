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
            'latitude' => $this->faker->latitude,
            'longitude' => $this->faker->longitude,
            'intermittent' => $this->faker->randomElement([null, 'yes', 'no']),
            'hidden_at' => null,
        ];
    }
} 