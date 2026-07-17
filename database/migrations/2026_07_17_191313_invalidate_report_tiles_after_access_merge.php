<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $springs = DB::table('springs')
            ->whereIn('id', DB::table('reports')
                ->select('spring_id')
                ->where('access_limited', true))
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get(['latitude', 'longitude']);

        foreach ($springs as $spring) {
            $this->invalidateTileRows('spring_tiles', [0, 5, 8], (float) $spring->longitude, (float) $spring->latitude);
            $this->invalidateTileRows('watered_spring_tiles', [0, 5], (float) $spring->longitude, (float) $spring->latitude);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Regenerated cache entries remain valid after rollback.
    }

    /** @param list<int> $zooms */
    private function invalidateTileRows(string $table, array $zooms, float $longitude, float $latitude): void
    {
        foreach ($zooms as $zoom) {
            $tileCount = 2 ** $zoom;
            $x = (int) floor((($longitude + 180) / 360) * $tileCount);
            $y = (int) floor((1 - log(tan(deg2rad($latitude)) + 1 / cos(deg2rad($latitude))) / pi()) / 2 * $tileCount);

            DB::table($table)
                ->where('z', $zoom)
                ->where('x', $x)
                ->where('y', $y)
                ->update(['generated_at' => null]);
        }
    }
};
