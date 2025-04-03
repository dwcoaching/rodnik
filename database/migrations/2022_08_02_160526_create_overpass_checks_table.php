<?php

use App\Models\OverpassCheck;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('overpass_checks', function (Blueprint $table) {
            $table->id();
            $table->decimal('latitude_from', 9, 6)->nullable();
            $table->decimal('latitude_to', 9, 6)->nullable();
            $table->decimal('longitude_from', 9, 6)->nullable();
            $table->decimal('longitude_to', 9, 6)->nullable();
            $table->foreignId('covered_by')->nullable();
            $table->timestamps();
        });

        $data = [];
        for ($latitude = -90; $latitude <= 89; $latitude = $latitude + 1) {
            for ($longitude = -180; $longitude <= 179; $longitude = $longitude + 1) {
                $data[] = [
                    'latitude_from' => $latitude,
                    'latitude_to' => $latitude + 1,
                    'longitude_from' => $longitude,
                    'longitude_to' => $longitude + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }
        
        foreach (array_chunk($data, 1000) as $chunk) {
            DB::table('overpass_checks')->insert($chunk);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('overpass_checks');
    }
};
