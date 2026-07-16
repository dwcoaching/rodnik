<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ReportQuality;
use App\Enums\ReportState;
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
            'quality' => $this->faker->randomElement(ReportQuality::cases()),
            'state' => $this->faker->randomElement([
                ReportState::Dry,
                ReportState::Dripping,
                ReportState::Running,
            ]),
            'access' => null,
            'littered' => null,
            'ruined' => null,
            'comment' => $this->faker->sentences(3, true),
            'user_id' => User::factory(),
        ];
    }
}
