<?php

namespace Database\Factories;

use App\Models\OSMTag;
use App\Models\Spring;
use Illuminate\Database\Eloquent\Factories\Factory;

class OSMTagFactory extends Factory
{
    protected $model = OSMTag::class;

    public function definition(): array
    {
        return [
            'key' => $this->faker->word,
            'value' => $this->faker->word,
            'spring_id' => Spring::factory(),
        ];
    }
} 