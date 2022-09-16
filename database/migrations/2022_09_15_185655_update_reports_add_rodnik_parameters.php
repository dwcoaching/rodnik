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
        Schema::table('reports', function (Blueprint $table) {
            $table->decimal('new_latitude', 9, 6)->nullable();
            $table->decimal('new_longitude', 9, 6)->nullable();
            $table->string('new_name')->nullable();
            $table->string('new_type')->nullable();
            $table->string('new_intermittent')->nullable();

            $table->decimal('old_latitude', 9, 6)->nullable();
            $table->decimal('old_longitude', 9, 6)->nullable();
            $table->string('old_name')->nullable();
            $table->string('old_type')->nullable();
            $table->string('old_intermittent')->nullable();

            $table->boolean('from_osm')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn('new_latitude');
            $table->dropColumn('new_longitude');
            $table->dropColumn('new_name');
            $table->dropColumn('new_type');
            $table->dropColumn('new_intermittent');

            $table->dropColumn('old_latitude');
            $table->dropColumn('old_longitude');
            $table->dropColumn('old_name');
            $table->dropColumn('old_type');
            $table->dropColumn('old_intermittent');

            $table->dropColumn('from_osm');
        });
    }
};
