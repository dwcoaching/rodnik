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
        Schema::table('springs', function (Blueprint $table) {
            $table->string('intermittent')->nullable();
            $table->dropColumn('seasonal')->nullable();

            $table->decimal('osm_latitude', 9, 6)->nullable();
            $table->decimal('osm_longitude', 9, 6)->nullable();
            $table->string('osm_name')->nullable();
            $table->string('osm_type')->nullable();
            $table->string('osm_intermittent')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('springs', function (Blueprint $table) {
            $table->boolean('seasonal')->nullable();
            $table->dropColumn('intermittent');

            $table->dropColumn('osm_latitude');
            $table->dropColumn('osm_longitude');
            $table->dropColumn('osm_name');
            $table->dropColumn('osm_type');
            $table->dropColumn('osm_intermittent');
        });
    }
};
