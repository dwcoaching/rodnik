<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (DB::table('maps')->lazyById(500) as $map) {
            $state = json_decode($map->state, true, flags: JSON_THROW_ON_ERROR);
            $filters = $state['filters'];

            if (! array_key_exists('confirmed', $filters) && array_key_exists('with_reports', $filters)) {
                continue;
            }

            unset($filters['confirmed']);
            $filters['with_reports'] ??= false;
            $state['filters'] = $filters;

            DB::table('maps')->where('id', $map->id)->where('version', $map->version)->update([
                'state' => json_encode($state, JSON_THROW_ON_ERROR),
            ]);
        }

        foreach (['tiles' => 'spring_tiles', 'watered-tiles' => 'watered_spring_tiles'] as $diskName => $table) {
            $disk = Storage::disk($diskName);
            $paths = array_values(array_filter($disk->allFiles(), fn (string $path): bool => str_ends_with($path, '.json')));

            foreach (array_chunk($paths, 500) as $chunk) {
                if (! $disk->delete($chunk)) {
                    throw new RuntimeException('Could not invalidate cached map tiles on '.$diskName.'.');
                }
            }

            DB::table($table)->update(['generated_at' => null]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Removed filter settings and regenerated cache files are not restored.
    }
};
