<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Country> */
final class CountryFactory extends Factory
{
    protected $model = Country::class;

    public function definition(): array
    {
        $name = fake()->country();

        return ['code' => fake()->unique()->countryCode(), 'name' => $name, 'name_ru' => null, 'slug' => Str::slug($name).'-'.Str::random(8), 'is_active' => true];
    }
}
