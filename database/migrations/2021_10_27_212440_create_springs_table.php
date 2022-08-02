<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSpringsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('springs', function (Blueprint $table) {
            $table->id();
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
            $table->string('name')->nullable();
            $table->unsignedBigInteger('osm_node_id')->nullable();
            $table->unsignedBigInteger('osm_way_id')->nullable();
            $table->timestamps();
        });

        Artisan::call('db:seed --class=SpringsSeeder --force');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('springs');
    }
}
