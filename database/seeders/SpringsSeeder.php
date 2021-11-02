<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SpringsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('springs')->insert([
            [
                'latitude' => 55.65514,
                'longitude' => 36.71009,
                'name' => 'Шараповский родник',
            ],
            [
                'latitude' => 55.65519,
                'longitude' => 36.71025,
                'name' => 'Тут совсем нет родника',
            ]
        ]);
    }
}
