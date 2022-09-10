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
            $table->string('type')->nullable();
            $table->boolean('seasonal')->nullable();
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
            $table->dropColumn('type');
            $table->dropColumn('seasonal');
        });
    }
};
