<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    /**
     * Move track and track polygon geometry from JSON columns to files on the tracks disk.
     */
    public function up(): void
    {
        $disk = Storage::disk('tracks');

        foreach (DB::table('tracks')->select(['id', 'token', 'track'])->lazyById(1) as $track) {
            $disk->put('uploads/'.$track->token.'.json', $track->track);
        }

        foreach (DB::table('track_polygons')->select(['id', 'hash', 'polygon'])->lazyById(10) as $polygon) {
            $disk->put('polygons/'.$polygon->hash.'.json', $polygon->polygon);
        }

        Schema::table('tracks', function (Blueprint $table): void {
            $table->dropColumn('track');
        });

        Schema::table('track_polygons', function (Blueprint $table): void {
            $table->dropColumn('polygon');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $disk = Storage::disk('tracks');

        Schema::table('tracks', function (Blueprint $table): void {
            $table->json('track')->nullable();
        });

        Schema::table('track_polygons', function (Blueprint $table): void {
            $table->json('polygon')->nullable();
        });

        foreach (DB::table('tracks')->select(['id', 'token'])->lazyById(100) as $track) {
            DB::table('tracks')->where('id', $track->id)->update(['track' => $disk->get('uploads/'.$track->token.'.json')]);
        }

        foreach (DB::table('track_polygons')->select(['id', 'hash'])->lazyById(100) as $polygon) {
            DB::table('track_polygons')->where('id', $polygon->id)->update(['polygon' => $disk->get('polygons/'.$polygon->hash.'.json')]);
        }

        Schema::table('tracks', function (Blueprint $table): void {
            $table->json('track')->nullable(false)->change();
        });

        Schema::table('track_polygons', function (Blueprint $table): void {
            $table->json('polygon')->nullable(false)->change();
        });

        $disk->deleteDirectory('uploads');
        $disk->deleteDirectory('polygons');
    }
};
