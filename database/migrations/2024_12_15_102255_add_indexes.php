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
        Schema::table('photos', function (Blueprint $table) {
            $table->index(['report_id']);
        });

        Schema::table('spring_revisions', function (Blueprint $table) {
            $table->index(['spring_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->dropIndex(['report_id']);
        });

        Schema::table('spring_revisions', function (Blueprint $table) {
            $table->dropIndex(['spring_id']);
        });
    }
};
