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
            $table->string('country')->nullable();
            $table->string('country_ru')->nullable();
            $table->string('country_code', 3)->nullable()->index();
            $table->string('region')->nullable();
            $table->string('city')->nullable()->index();
            $table->string('geocoding_source')->nullable();
            $table->timestamp('geocoded_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('springs', function (Blueprint $table): void {
            $table->dropIndex(['country_code']);
            $table->dropIndex(['city']);
            $table->dropColumn(['country', 'country_ru', 'country_code', 'region', 'city', 'geocoding_source', 'geocoded_at']);
        });
    }
};
