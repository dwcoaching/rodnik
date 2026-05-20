<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('overpass_imports', function (Blueprint $table) {
            $table->index(
                ['overpass_batch_id', 'has_remarks', 'latitude_from', 'latitude_to', 'longitude_from', 'longitude_to'],
                'overpass_imports_coverage_bbox_idx'
            );
        });

        Schema::table('overpass_checks', function (Blueprint $table) {
            $table->index(['overpass_batch_id', 'covered_by'], 'overpass_checks_batch_covered_idx');
        });
    }

    public function down(): void
    {
        Schema::table('overpass_imports', function (Blueprint $table) {
            $table->dropIndex('overpass_imports_coverage_bbox_idx');
        });

        Schema::table('overpass_checks', function (Blueprint $table) {
            $table->dropIndex('overpass_checks_batch_covered_idx');
        });
    }
};
