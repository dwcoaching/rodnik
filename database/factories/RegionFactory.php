<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Country;
use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Region> */
final class RegionFactory extends Factory
{
    protected $model = Region::class;

    public function definition(): array
    {
        $name = fake()->state();

        return ['country_id' => Country::factory(), 'source_key' => 'fixture:'.Str::uuid(), 'source_country_code' => 'RU', 'admin1_code' => '01', 'name' => $name, 'name_ru' => null, 'slug' => Str::slug($name).'-'.Str::random(8), 'is_active' => true];
    }
}
