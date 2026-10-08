<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cities', function (Blueprint $table): void {
            // Name prefixes use the B-tree indexes; the full-text index finds
            // later words such as "Novgorod" in "Velikiy Novgorod".
            $table->index(['name', 'population'], 'cities_name_search_index');
            $table->index(['name_ru', 'population'], 'cities_name_ru_search_index');
            $table->fullText(['name', 'name_ru'], 'cities_names_fulltext');
        });

        foreach (['countries', 'regions'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                // West/south/east/north in degrees. East exceeds 180 when the
                // area crosses the antimeridian so the box stays continuous.
                $table->decimal('bbox_west', 11, 7)->nullable();
                $table->decimal('bbox_south', 10, 7)->nullable();
                $table->decimal('bbox_east', 11, 7)->nullable();
                $table->decimal('bbox_north', 10, 7)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['countries', 'regions'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn(['bbox_west', 'bbox_south', 'bbox_east', 'bbox_north']);
            });
        }

        Schema::table('cities', function (Blueprint $table): void {
            $table->dropFullText('cities_names_fulltext');
            $table->dropIndex('cities_name_search_index');
            $table->dropIndex('cities_name_ru_search_index');
        });
    }
};
