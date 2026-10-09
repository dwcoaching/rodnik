<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 3)->unique();
            $table->unsignedBigInteger('geonames_id')->nullable()->unique();
            $table->string('name');
            $table->string('name_ru')->nullable();
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true)->index();
            $table->uuid('catalog_generation')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('regions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('country_id')->constrained()->restrictOnDelete();
            $table->string('source_key', 100)->unique();
            $table->string('source_country_code', 3);
            $table->string('admin1_code', 20)->nullable();
            $table->unsignedBigInteger('geonames_id')->nullable()->unique();
            $table->string('iso_code', 20)->nullable()->index();
            $table->string('name');
            $table->string('name_ru')->nullable();
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true)->index();
            $table->uuid('catalog_generation')->nullable()->index();
            $table->timestamps();
            $table->index(['source_country_code', 'admin1_code']);
        });

        Schema::create('cities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('country_id')->constrained()->restrictOnDelete();
            $table->foreignId('region_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('geonames_id')->unique();
            $table->string('source_country_code', 3);
            $table->string('admin1_code', 20)->nullable();
            $table->string('name');
            $table->string('name_ru')->nullable();
            $table->string('slug')->unique();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 11, 7);
            $table->unsignedBigInteger('population')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->uuid('catalog_generation')->nullable()->index();
            $table->timestamps();
            $table->index(['country_id', 'is_active']);
            $table->index(['region_id', 'is_active']);
            $table->index(['latitude', 'longitude']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cities');
        Schema::dropIfExists('regions');
        Schema::dropIfExists('countries');
    }
};
