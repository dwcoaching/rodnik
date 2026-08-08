<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // One row per request actually sent to Overpass. Kept separate from `overpass_imports`
        // because a retry overwrites the import's own columns, which hid a 34% refusal rate
        // behind a table that looked like a clean 360/360 run. The geometry is duplicated here
        // on purpose so the statistics outlive the imports, which get pruned between batches.
        Schema::create('overpass_attempts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('overpass_batch_id')->index();
            $table->unsignedBigInteger('overpass_import_id')->index();
            $table->unsignedSmallInteger('attempt');

            $table->decimal('latitude_from', 9, 6);
            $table->decimal('latitude_to', 9, 6);
            $table->decimal('longitude_from', 9, 6);
            $table->decimal('longitude_to', 9, 6);

            $table->dateTime('started_at');
            $table->unsignedInteger('duration_seconds');
            $table->unsignedSmallInteger('response_code');
            $table->unsignedBigInteger('response_bytes')->default(0);
            $table->unsignedInteger('element_count')->nullable();
            $table->boolean('congested')->default(false);

            $table->timestamps();
        });

        // Seed from whatever has already been fetched, so the first batch after this migration
        // can be planned from real timings instead of falling back to the uniform grid. Only
        // the last attempt of each import survives in `overpass_imports`, which is the
        // successful one, and that is exactly the cost we want to plan against.
        DB::statement('
            INSERT INTO overpass_attempts (
                overpass_batch_id, overpass_import_id, attempt,
                latitude_from, latitude_to, longitude_from, longitude_to,
                started_at, duration_seconds, response_code, congested,
                created_at, updated_at
            )
            SELECT
                overpass_batch_id, id, GREATEST(attempts, 1),
                latitude_from, latitude_to, longitude_from, longitude_to,
                started_at, TIMESTAMPDIFF(SECOND, started_at, fetched_at),
                CAST(response_code AS UNSIGNED), response_code <> 200,
                NOW(), NOW()
            FROM overpass_imports
            WHERE started_at IS NOT NULL
              AND fetched_at IS NOT NULL
              AND TIMESTAMPDIFF(SECOND, started_at, fetched_at) >= 0
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('overpass_attempts');
    }
};
