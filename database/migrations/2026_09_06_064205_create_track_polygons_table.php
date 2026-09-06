<?php

declare(strict_types=1);

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
        Schema::create('track_polygons', function (Blueprint $table): void {
            $table->id();
            $table->char('hash', 64)->unique();
            $table->json('polygon');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('latitude_from', 10, 7)->index();
            $table->decimal('latitude_to', 10, 7);
            $table->decimal('longitude_from', 10, 7)->index();
            $table->decimal('longitude_to', 10, 7);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('track_polygons');
    }
};
