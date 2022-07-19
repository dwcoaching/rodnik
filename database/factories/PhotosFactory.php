<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class PhotosFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'reviewed_at' => fake()->dateTimeThisYear(),
            'spring_id' => fake()->randomElement([1, 2]),
            'quality' => fake()->randomElement(['bad', 'uncertain', 'good']),
            'state' => fake()->randomElement(['dry', 'dripping', 'running']),
            'comment' => fake()->sentences(3, true),
        ];
    }
}
