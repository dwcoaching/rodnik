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
        Schema::rename('reviews', 'reports');

        Schema::table('photos', function (Blueprint $table) {
            $table->renameColumn('review_id', 'report_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::rename('reports', 'reviews');

        Schema::table('photos', function (Blueprint $table) {
            $table->renameColumn('report_id', 'review_id');
        });
    }
};
