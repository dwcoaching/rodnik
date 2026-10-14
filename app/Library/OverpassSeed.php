<?php

declare(strict_types=1);

namespace App\Library;

use App\Models\OverpassAttempt;
use Illuminate\Support\Facades\DB;

/**
 * Works out how to carve the globe into areas that each cost Overpass about the same.
 *
 * The old seed cut 360 identical pole-to-pole strips one degree wide, which is the one thing
 * the world is not: measured over a full import, an empty Pacific strip answered in 4 seconds
 * while the strip over Germany took 157. That spread is expensive at both ends — the cheap
 * areas waste a pacing slot, and the dense ones creep towards the query's 180 second timeout.
 *
 * Rather than predict cost from a formula, the plan is built from what previous requests
 * actually took: {@see OverpassAttempt} records the duration of every request alongside the
 * area it covered, and those seconds are spread back over the 1x1 degree cells the area
 * contained. Empty columns then merge into wider blocks and dense ones split into latitude
 * bands, all against measured seconds rather than a guess.
 */
final class OverpassSeed
{
    /**
     * What each area should cost. Comfortably under the query's
     * {@see \App\Models\OverpassImport::QUERY_TIMEOUT_SECONDS}, and above
     * {@see OverpassGate::minimumCycleSeconds()} so that pacing never idles.
     */
    public const TARGET_SECONDS = 60.0;

    /**
     * The most an area may send back. Seconds are a poor guide to this on maps.mail.ru, where
     * most of a request is spent queueing: the wells of southern India come back as 70 MB in
     * barely half a minute, so on time alone they were merged with their neighbours into a 73 MB
     * answer the workers could not decode. Kept well below
     * {@see \App\Models\OverpassImport::MAXIMUM_RESPONSE_BYTES}.
     */
    public const TARGET_BYTES = 20_000_000.0;

    /**
     * Ignore measurements older than this many batches; OSM density drifts.
     */
    public const BATCHES_OF_HISTORY = 2;

    /**
     * Build the seed from measured timings, falling back to the uniform grid without them.
     *
     * @return array<int, array{latitude_from: int, latitude_to: int, longitude_from: int, longitude_to: int}>
     */
    public static function segments(): array
    {
        $costs = self::measuredCellCosts();

        if ($costs === []) {
            return self::uniformSegments();
        }

        return self::segmentsFor($costs, self::measuredCellBytes());
    }

    /**
     * Measured seconds per 1x1 degree cell, keyed by [longitude][latitude].
     *
     * Each successful request contributes its own duration, spread across the cells it covered
     * in proportion to the springs in them. The `1 +` keeps empty cells from being free: an
     * ocean area really did take a few seconds, and that cost is spread evenly across it.
     *
     * @return array<int, array<int, float>>
     */
    public static function measuredCellCosts(): array
    {
        return self::spreadOverCells(fn (OverpassAttempt $attempt): float => (float) $attempt->duration_seconds);
    }

    /**
     * Measured response bytes per 1x1 degree cell, keyed by [longitude][latitude], spread the
     * same way. Bytes follow the springs far more closely than seconds do.
     *
     * @return array<int, array<int, float>>
     */
    public static function measuredCellBytes(): array
    {
        return self::spreadOverCells(fn (OverpassAttempt $attempt): float => (float) $attempt->response_bytes);
    }

    /**
     * The 1x1 degree cells an attempt covered.
     *
     * @return array<int, array{0: int, 1: int}>
     */
    public static function cellsOf(OverpassAttempt $attempt): array
    {
        $cells = [];

        $longitudeFrom = (int) floor((float) $attempt->longitude_from);
        $longitudeTo = (int) ceil((float) $attempt->longitude_to);
        $latitudeFrom = (int) floor((float) $attempt->latitude_from);
        $latitudeTo = (int) ceil((float) $attempt->latitude_to);

        for ($longitude = $longitudeFrom; $longitude < $longitudeTo; $longitude++) {
            for ($latitude = $latitudeFrom; $latitude < $latitudeTo; $latitude++) {
                $cells[] = [$longitude, $latitude];
            }
        }

        return $cells;
    }

    /**
     * Known springs per 1x1 degree cell, keyed by [longitude][latitude].
     *
     * @return array<int, array<int, int>>
     */
    public static function springDensity(): array
    {
        $rows = DB::table('springs')
            ->whereNotNull('osm_latitude')
            ->whereNotNull('osm_longitude')
            ->whereNull('hidden_at')
            ->groupBy('longitude_cell', 'latitude_cell')
            ->select([
                DB::raw('FLOOR(osm_longitude) as longitude_cell'),
                DB::raw('FLOOR(osm_latitude) as latitude_cell'),
                DB::raw('COUNT(*) as springs'),
            ])
            ->get();

        $density = [];

        foreach ($rows as $row) {
            $density[(int) $row->longitude_cell][(int) $row->latitude_cell] = (int) $row->springs;
        }

        return $density;
    }

    /**
     * Pack a cost map into areas of roughly {@see self::TARGET_SECONDS} each, none of them
     * expected to send back more than {@see self::TARGET_BYTES}.
     *
     * @param  array<int, array<int, float>>  $costs  measured seconds keyed by [longitude][latitude]
     * @param  array<int, array<int, float>>  $bytes  measured response bytes, keyed the same way
     * @return array<int, array{latitude_from: int, latitude_to: int, longitude_from: int, longitude_to: int}>
     */
    public static function segmentsFor(array $costs, array $bytes = []): array
    {
        $segments = [];
        $runFrom = null;
        $runTo = null;
        $runCost = 0.0;
        $runBytes = 0.0;

        for ($longitude = -180; $longitude < 180; $longitude++) {
            $column = $costs[$longitude] ?? [];
            $cost = array_sum($column);
            $byteColumn = $bytes[$longitude] ?? [];
            $size = array_sum($byteColumn);

            $tooExpensiveToMerge = $cost > self::TARGET_SECONDS || $size > self::TARGET_BYTES;
            $wouldOverflowTheRun = $runFrom !== null
                && ($runCost + $cost > self::TARGET_SECONDS || $runBytes + $size > self::TARGET_BYTES);

            if (($tooExpensiveToMerge || $wouldOverflowTheRun) && $runFrom !== null) {
                $segments[] = self::wholeColumns($runFrom, $runTo);
                $runFrom = null;
                $runCost = 0.0;
                $runBytes = 0.0;
            }

            if ($tooExpensiveToMerge) {
                // Split along whichever measure needs more bands, so both end up within target.
                $bandsForCost = (int) ceil($cost / self::TARGET_SECONDS);
                $bandsForSize = (int) ceil($size / self::TARGET_BYTES);

                $bands = $bandsForSize > $bandsForCost
                    ? self::latitudeBands($byteColumn, $size, $bandsForSize)
                    : self::latitudeBands($column, $cost);

                foreach ($bands as $band) {
                    $segments[] = [
                        'latitude_from' => $band[0],
                        'latitude_to' => $band[1],
                        'longitude_from' => $longitude,
                        'longitude_to' => $longitude + 1,
                    ];
                }

                continue;
            }

            $runFrom ??= $longitude;
            $runTo = $longitude;
            $runCost += $cost;
            $runBytes += $size;
        }

        if ($runFrom !== null) {
            $segments[] = self::wholeColumns($runFrom, $runTo);
        }

        return $segments;
    }

    /**
     * Split one column into bands of roughly equal measured weight.
     *
     * Splitting evenly rather than filling each band to the target avoids leaving a tiny
     * straggler band behind, which would cost a whole pacing slot to fetch almost nothing.
     *
     * @param  array<int, float>  $column  measured seconds (or bytes) keyed by latitude
     * @param  int|null  $bands  how many bands to aim for; by default enough for the time target
     * @return array<int, array{0: int, 1: int}>
     */
    public static function latitudeBands(array $column, float $total, ?int $bands = null): array
    {
        $bands = max(2, $bands ?? (int) ceil($total / self::TARGET_SECONDS));
        $share = $total / $bands;

        if ($share <= 0) {
            return [[-90, 90]];
        }

        $edges = [];
        $running = 0.0;

        for ($latitude = -90; $latitude < 90; $latitude++) {
            $running += $column[$latitude] ?? 0;

            if (count($edges) < $bands - 1 && $running >= $share * (count($edges) + 1)) {
                $edges[] = $latitude + 1;
            }
        }

        $bounds = array_merge([-90], $edges, [90]);
        $out = [];

        for ($i = 0; $i < count($bounds) - 1; $i++) {
            if ($bounds[$i] < $bounds[$i + 1]) {
                $out[] = [$bounds[$i], $bounds[$i + 1]];
            }
        }

        return $out;
    }

    /**
     * The original seed: 360 pole-to-pole strips one degree wide.
     *
     * @return array<int, array{latitude_from: int, latitude_to: int, longitude_from: int, longitude_to: int}>
     */
    public static function uniformSegments(): array
    {
        $segments = [];

        for ($longitude = -180; $longitude < 180; $longitude++) {
            $segments[] = [
                'latitude_from' => -90,
                'latitude_to' => 90,
                'longitude_from' => $longitude,
                'longitude_to' => $longitude + 1,
            ];
        }

        return $segments;
    }

    /**
     * Spread a measurement of every successful recent request over the cells it covered.
     *
     * @param  callable(OverpassAttempt): float  $measure
     * @return array<int, array<int, float>>
     */
    private static function spreadOverCells(callable $measure): array
    {
        $batches = OverpassAttempt::query()
            ->distinct()
            ->orderByDesc('overpass_batch_id')
            ->limit(self::BATCHES_OF_HISTORY)
            ->pluck('overpass_batch_id');

        if ($batches->isEmpty()) {
            return [];
        }

        $density = self::springDensity();
        $totals = [];
        $samples = [];

        $attempts = OverpassAttempt::query()
            ->whereIn('overpass_batch_id', $batches)
            ->where('response_code', 200)
            ->where('congested', false)
            ->get();

        foreach ($attempts as $attempt) {
            $cells = self::cellsOf($attempt);

            if ($cells === []) {
                continue;
            }

            $weight = 0.0;

            foreach ($cells as [$longitude, $latitude]) {
                $weight += 1 + ($density[$longitude][$latitude] ?? 0);
            }

            $value = $measure($attempt);

            foreach ($cells as [$longitude, $latitude]) {
                $share = (1 + ($density[$longitude][$latitude] ?? 0)) / $weight;

                $totals[$longitude][$latitude] = ($totals[$longitude][$latitude] ?? 0.0) + $value * $share;
                $samples[$longitude][$latitude] = ($samples[$longitude][$latitude] ?? 0) + 1;
            }
        }

        // A cell covered by several batches has accumulated several batches' worth.
        foreach ($totals as $longitude => $column) {
            foreach ($column as $latitude => $total) {
                $totals[$longitude][$latitude] = $total / $samples[$longitude][$latitude];
            }
        }

        return $totals;
    }

    /**
     * A block spanning whole longitude columns, pole to pole.
     *
     * @return array{latitude_from: int, latitude_to: int, longitude_from: int, longitude_to: int}
     */
    private static function wholeColumns(int $from, int $to): array
    {
        return [
            'latitude_from' => -90,
            'latitude_to' => 90,
            'longitude_from' => $from,
            'longitude_to' => $to + 1,
        ];
    }
}
