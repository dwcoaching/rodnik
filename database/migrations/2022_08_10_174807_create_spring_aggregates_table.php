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
        Schema::create('spring_aggregates', function (Blueprint $table) {
            $table->id();

            $table->decimal('latitude', 9, 6)->nullable(); // center point
            $table->decimal('longitude', 9, 6)->nullable(); // center point

            $table->integer('count')->nullable();
            $table->float('step', 5, 2)->nullable();

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
        Schema::dropIfExists('spring_aggregates');
    }
};
