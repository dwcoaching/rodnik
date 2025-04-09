<?php

namespace Database\Seeders;

use App\Models\OverpassCheck;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OverpassCheckSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Skip seeding during tests to improve performance
        if (app()->environment('testing')) {
            return;
        }

        // Use chunk insertion for better performance
        $batchSize = 1000;
        $records = [];
        $count = 0;

        for ($latitude = -90; $latitude <= 89; $latitude++) {
            for ($longitude = -180; $longitude <= 179; $longitude++) {
                $records[] = [
                    'latitude_from' => $latitude,
                    'latitude_to' => $latitude + 1,
                    'longitude_from' => $longitude,
                    'longitude_to' => $longitude + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $count++;

                if ($count >= $batchSize) {
                    DB::table('overpass_checks')->insert($records);
                    $records = [];
                    $count = 0;
                }
            }
        }

        // Insert any remaining records
        if (count($records) > 0) {
            DB::table('overpass_checks')->insert($records);
        }
    }
}
