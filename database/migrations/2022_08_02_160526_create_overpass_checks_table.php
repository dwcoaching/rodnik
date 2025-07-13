<?php

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
