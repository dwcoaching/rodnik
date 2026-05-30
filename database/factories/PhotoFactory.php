<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Photo;
use App\Models\Report;
use Illuminate\Database\Eloquent\Factories\Factory;

final class PhotoFactory extends Factory
{
    protected $model = Photo::class;

    public function definition(): array
    {
        return [
            'report_id' => null,
            'original_filename' => $this->faker->uuid().'.jpg',
            'original_extension' => 'jpg',
            'extension' => 'jpg',
            'latitude' => null,
            'longitude' => null,
            'width' => 640,
            'height' => 480,
            'order' => null,
        ];
    }

    public function attached(): self
    {
        return $this->state(fn () => [
            'report_id' => Report::factory(),
        ]);
    }
}
