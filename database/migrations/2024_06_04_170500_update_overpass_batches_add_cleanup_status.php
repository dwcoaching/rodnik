<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('overpass_batches', function (Blueprint $table) {
            $table->string('cleanup_status')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('overpass_batches', function (Blueprint $table) {
            $table->dropColumn('cleanup_status');
        });
    }
};
