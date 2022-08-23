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
            $table->dateTime('hidden_at')->nullable();
            $table->foreignId('hidden_by_author_id')->nullable();
            $table->foreignId('hidden_by_moderator_id')->nullable();
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
            $table->dropColumn('hidden_at');
            $table->dropColumn('hidden_by_author_id');
            $table->dropColumn('hidden_by_moderator_id');
        });
    }
};
