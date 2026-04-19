<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('springs', function (Blueprint $table) {
            $table->unsignedBigInteger('osm_version')->nullable();
            $table->unsignedBigInteger('last_seen_overpass_batch_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('springs', function (Blueprint $table) {
            $table->dropIndex(['last_seen_overpass_batch_id']);
            $table->dropColumn(['osm_version', 'last_seen_overpass_batch_id']);
        });
    }
};
