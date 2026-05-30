<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Report;
use App\Models\Spring;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

final class ReportFactory extends Factory
{
    protected $model = Report::class;

    public function definition(): array
    {
        return [
            'visited_at' => $this->faker->date(),
            'spring_id' => Spring::factory(),
            'quality' => $this->faker->randomElement(['bad', 'uncertain', 'good']),
            'state' => $this->faker->randomElement(['dry', 'dripping', 'running']),
            'comment' => $this->faker->sentences(3, true),
            'user_id' => User::factory(),
        ];
    }
}
