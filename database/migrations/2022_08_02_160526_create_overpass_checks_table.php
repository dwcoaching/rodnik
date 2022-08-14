<?php

use App\Models\OverpassCheck;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

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

        $latitudeStart = -90;
        $longitude = -180;

        for ($latitude = -90; $latitude <= 89; $latitude = $latitude + 1) {
            for ($longitude = -180; $longitude <= 179; $longitude = $longitude + 1) {
                $overpassCheck = new OverpassCheck();
                $overpassCheck->latitude_from = $latitude;
                $overpassCheck->latitude_to = $latitude + 1;
                $overpassCheck->longitude_from = $longitude;
                $overpassCheck->longitude_to = $longitude + 1;
                $overpassCheck->save();
            }
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
