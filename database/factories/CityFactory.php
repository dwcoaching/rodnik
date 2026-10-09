<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\City;
use App\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<City> */
final class CityFactory extends Factory
{
    protected $model = City::class;

    public function definition(): array
    {
        $name = fake()->city();

        return [
            'country_id' => Country::factory(), 'region_id' => null, 'geonames_id' => fake()->unique()->numberBetween(1, 2_000_000_000),
            'source_country_code' => 'RU', 'name' => $name, 'name_ru' => null, 'slug' => Str::slug($name).'-'.Str::random(8),
            'latitude' => fake()->latitude(), 'longitude' => fake()->longitude(), 'is_active' => true,
        ];
    }
}
