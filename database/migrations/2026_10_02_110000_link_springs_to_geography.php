<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('springs', function (Blueprint $table): void {
            $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->foreignId('region_id')->nullable()->constrained('regions')->nullOnDelete();
            $table->foreignId('nearest_city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->decimal('nearest_city_distance_km', 10, 3)->nullable();
            $table->string('city_ru')->nullable();
            $table->string('region_ru')->nullable();
            $table->index(['country_id', 'hidden_at', 'redirect_to_spring_id'], 'springs_country_public_index');
            $table->index(['region_id', 'hidden_at', 'redirect_to_spring_id'], 'springs_region_public_index');
            $table->index(['nearest_city_id', 'hidden_at', 'redirect_to_spring_id'], 'springs_city_public_index');
        });
    }

    public function down(): void
    {
        Schema::table('springs', function (Blueprint $table): void {
            $table->dropForeign(['country_id']);
            $table->dropForeign(['region_id']);
            $table->dropForeign(['nearest_city_id']);
            $table->dropIndex('springs_country_public_index');
            $table->dropIndex('springs_region_public_index');
            $table->dropIndex('springs_city_public_index');
            $table->dropColumn(['country_id', 'region_id', 'nearest_city_id', 'nearest_city_distance_km', 'city_ru', 'region_ru']);
        });
    }
};
