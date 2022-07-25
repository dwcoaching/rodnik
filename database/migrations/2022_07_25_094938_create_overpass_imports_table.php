<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('overpass_imports', function (Blueprint $table) {
            $table->id();
            $table->decimal('latitude_from', 9, 6)->nullable();
            $table->decimal('latitude_to', 9, 6)->nullable();
            $table->decimal('longitude_from', 9, 6)->nullable();
            $table->decimal('longitude_to', 9, 6)->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('fetched_at')->nullable();
            $table->dateTime('parsed_at')->nullable();
            $table->text('query')->nullable();
            $table->longText('response')->nullable();
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
        Schema::dropIfExists('overpass_imports');
    }
};
