<?php

declare(strict_types=1);

namespace App\Services\SourceRaster;

use App\Models\Report;
use App\Models\Spring;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

/** Numeric source data is kept separately from the image style. */
final class RasterSnapshot
{
    public const TILE_SIZE = 512;

    public const MERCATOR_LATITUDE = 85.0511287798066;

    /** @return array{source_count: int, cell_count: int, max_zoom: int, seconds: float, bytes: int} */
    public static function build(string $path, int $maxZoom = 10, ?callable $progress = null): array
    {
        self::checkZoom($maxZoom);
        $started = microtime(true);
        $statuses = self::reportedStatuses();

        $points = (function () use ($maxZoom, $statuses) {
            // lazyById bounds memory and avoids hydrating a million Spring models.
            $sources = DB::table('springs')
                ->select(['id', 'longitude', 'latitude', 'type'])
                ->whereNull('hidden_at')
                ->whereNull('redirect_to_spring_id')
                ->lazyById(10000);

            foreach ($sources as $source) {
                if (! is_numeric($source->longitude) || ! is_numeric($source->latitude)) {
                    continue;
                }

                $position = self::project((float) $source->longitude, (float) $source->latitude, $maxZoom);

                if ($position === null) {
                    continue;
                }

                yield [
                    'id' => (int) $source->id,
                    ...$position,
                    'type' => self::typeBit($source->type),
                    'status' => $statuses[$source->id] ?? 0,
                    'longitude' => (float) $source->longitude === 180.0 ? -180.0 : (float) $source->longitude,
                    'latitude' => (float) $source->latitude,
                ];
            }
        })();

        $stats = self::populateFromPoints($path, $points, $maxZoom, $progress);
        $stats['seconds'] = microtime(true) - $started;

        return $stats;
    }

    /**
     * Build a fresh unpublished file. The caller publishes it with an atomic rename.
     *
     * @param  iterable<array{x: int, y: int, type: int, status: int}>  $points
     * @return array{source_count: int, cell_count: int, max_zoom: int, seconds: float, bytes: int}
     */
    public static function populateFromPoints(string $path, iterable $points, int $maxZoom = 10, ?callable $progress = null): array
    {
        self::checkZoom($maxZoom);

        if (file_exists($path)) {
            throw new RuntimeException("Raster snapshot already exists [{$path}].");
        }

        $started = microtime(true);
        $database = null;

        try {
            $database = self::connection($path);
            // This is a disposable staging file. Published snapshots are never
            // written in place, so a crash cannot damage the currently served set.
            $database->exec('PRAGMA journal_mode = OFF; PRAGMA synchronous = OFF; PRAGMA cache_size = -65536; PRAGMA temp_store = FILE');
            $database->exec('CREATE TABLE metadata (key TEXT PRIMARY KEY, value TEXT NOT NULL) WITHOUT ROWID');
            // Sources are only staged here to be counted; the snapshot keeps counts alone.
            $database->exec('CREATE TEMP TABLE points (
                x INTEGER NOT NULL, y INTEGER NOT NULL,
                type INTEGER NOT NULL, status INTEGER NOT NULL
            )');
            $database->exec('CREATE TABLE cells (
                z INTEGER NOT NULL, tile_x INTEGER NOT NULL, tile_y INTEGER NOT NULL,
                px INTEGER NOT NULL, py INTEGER NOT NULL,
                type INTEGER NOT NULL, status INTEGER NOT NULL, n INTEGER NOT NULL,
                PRIMARY KEY (z, tile_x, tile_y, px, py, type, status)
            ) WITHOUT ROWID');
            $database->beginTransaction();
            $insert = $database->prepare('INSERT INTO points (x, y, type, status) VALUES (?, ?, ?, ?)');
            $sourceCount = 0;
            $worldSize = self::TILE_SIZE * (1 << $maxZoom);

            foreach ($points as $point) {
                if ($point['x'] < 0 || $point['y'] < 0 || $point['x'] >= $worldSize || $point['y'] >= $worldSize
                    || ! in_array($point['type'], [1, 2, 4, 8, 16, 32], true)
                    || $point['status'] < 0 || $point['status'] > 4) {
                    throw new InvalidArgumentException('Invalid raster snapshot point.');
                }

                $insert->execute([$point['x'], $point['y'], $point['type'], $point['status']]);
                $sourceCount++;

                if ($sourceCount % 100000 === 0) {
                    $progress && $progress('sources', $sourceCount);
                }
            }

            $insert = null;
            $progress && $progress('sources', $sourceCount);
            $database->exec("INSERT INTO cells (z, tile_x, tile_y, px, py, type, status, n)
                SELECT {$maxZoom}, x / 512, y / 512, x % 512, y % 512, type, status, COUNT(*)
                FROM points WHERE status <> 4
                GROUP BY x / 512, y / 512, x % 512, y % 512, type, status");
            $cellCount = (int) $database->query('SELECT changes()')->fetchColumn();
            $progress && $progress('zoom', $maxZoom);

            // Sum numeric children; resizing colored PNGs would erase sparse
            // conditions and mix red and green into misleading colors.
            for ($zoom = $maxZoom - 1; $zoom >= 0; $zoom--) {
                $childZoom = $zoom + 1;
                $database->exec("INSERT INTO cells (z, tile_x, tile_y, px, py, type, status, n)
                    SELECT {$zoom}, tile_x / 2, tile_y / 2,
                        (tile_x % 2) * 256 + px / 2, (tile_y % 2) * 256 + py / 2,
                        type, status, SUM(n)
                    FROM cells WHERE z = {$childZoom}
                    GROUP BY tile_x / 2, tile_y / 2,
                        (tile_x % 2) * 256 + px / 2, (tile_y % 2) * 256 + py / 2,
                        type, status");
                $cellCount += (int) $database->query('SELECT changes()')->fetchColumn();
                $progress && $progress('zoom', $zoom);
            }

            $metadata = $database->prepare('INSERT INTO metadata (key, value) VALUES (?, ?)');

            foreach (['version' => 1, 'max_zoom' => $maxZoom, 'source_count' => $sourceCount, 'tile_size' => self::TILE_SIZE] as $key => $value) {
                $metadata->execute([$key, (string) $value]);
            }

            $database->commit();
            $database->exec('DROP TABLE points; PRAGMA optimize');
            $database = null;
            clearstatcache(true, $path);

            return [
                'source_count' => $sourceCount,
                'cell_count' => $cellCount,
                'max_zoom' => $maxZoom,
                'seconds' => microtime(true) - $started,
                'bytes' => (int) filesize($path),
            ];
        } catch (Throwable $exception) {
            if ($database?->inTransaction()) {
                $database->rollBack();
            }

            $database = null;
            @unlink($path);

            throw $exception;
        }
    }

    /** Readers cannot write by accident; only refresh opens the snapshot writable. */
    public static function open(string $path, bool $writable = false): PDO
    {
        if (! is_file($path)) {
            throw new RuntimeException("Raster snapshot does not exist [{$path}].");
        }

        $database = self::connection($path);
        // Refresh commits briefly while web requests read; wait instead of failing.
        $database->exec('PRAGMA busy_timeout = 5000; PRAGMA cache_size = -8192');
        if (! $writable) {
            $database->exec('PRAGMA query_only = ON');
        }

        return $database;
    }

    /** @return array{x: int, y: int}|null */
    public static function project(float $longitude, float $latitude, int $maxZoom): ?array
    {
        self::checkZoom($maxZoom);

        if (! is_finite($longitude) || ! is_finite($latitude) || abs($longitude) > 180 || abs($latitude) > 90) {
            return null;
        }

        $worldSize = self::TILE_SIZE * (1 << $maxZoom);
        $longitude = $longitude === 180.0 ? -180.0 : $longitude;
        $latitude = max(-self::MERCATOR_LATITUDE, min(self::MERCATOR_LATITUDE, $latitude));
        $radians = deg2rad($latitude);

        return [
            'x' => min($worldSize - 1, max(0, (int) floor(($longitude + 180) / 360 * $worldSize))),
            'y' => min($worldSize - 1, max(0, (int) floor((1 - asinh(tan($radians)) / M_PI) / 2 * $worldSize))),
        ];
    }

    /** @return array{id: int, x: int, y: int, type: int, status: int, longitude: float, latitude: float}|null */
    public static function pointFromSpring(Spring $spring, int $maxZoom): ?array
    {
        if ($spring->hidden_at !== null || $spring->redirect_to_spring_id !== null
            || ! is_numeric($spring->longitude) || ! is_numeric($spring->latitude)) {
            return null;
        }

        $position = self::project((float) $spring->longitude, (float) $spring->latitude, $maxZoom);

        if ($position === null) {
            return null;
        }

        return [
            'id' => (int) $spring->id,
            ...$position,
            'type' => self::typeBit($spring->type),
            'status' => self::statusFromReports($spring->visibleReports),
            'longitude' => (float) $spring->longitude === 180.0 ? -180.0 : (float) $spring->longitude,
            'latitude' => (float) $spring->latitude,
        ];
    }

    /** Fresh DB contents of the finest pixel, for coordinate-based invalidation. */
    public static function pointsNearCoordinates(float $longitude, float $latitude, int $maxZoom): array
    {
        $position = self::project($longitude, $latitude, $maxZoom);

        if ($position === null) {
            return [];
        }

        $worldSize = self::TILE_SIZE * (1 << $maxZoom);
        $west = $position['x'] / $worldSize * 360 - 180;
        $east = ($position['x'] + 1) / $worldSize * 360 - 180;
        $north = rad2deg(atan(sinh(M_PI * (1 - 2 * $position['y'] / $worldSize))));
        $south = rad2deg(atan(sinh(M_PI * (1 - 2 * ($position['y'] + 1) / $worldSize))));
        $query = Spring::query()->publicGeography()->withVisibleReportConditions()
            ->where('latitude', '>=', $position['y'] === $worldSize - 1 ? -90 : $south - 1e-9)
            ->where('latitude', '<=', $position['y'] === 0 ? 90 : $north + 1e-9)
            ->where(function ($query) use ($west, $east) {
                $query->where(function ($query) use ($west, $east) {
                    $query->where('longitude', '>=', $west - 1e-9)->where('longitude', '<=', $east + 1e-9);
                });

                if ($west === -180.0) {
                    $query->orWhere('longitude', 180);
                }
            });

        return $query->get()->map(fn (Spring $spring): ?array => self::pointFromSpring($spring, $maxZoom))
            ->filter(fn (?array $point): bool => $point !== null && $point['x'] === $position['x'] && $point['y'] === $position['y'])
            ->values()->all();
    }

    public static function typeBit(?string $type): int
    {
        $index = array_search($type, Spring::TYPES, true);

        return $index === false ? 32 : 1 << $index;
    }

    private static function connection(string $path): PDO
    {
        return new PDO('sqlite:'.$path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ]);
    }

    private static function checkZoom(int $maxZoom): void
    {
        if ($maxZoom < 0 || $maxZoom > 20) {
            throw new InvalidArgumentException('Raster maximum zoom must be between 0 and 20.');
        }
    }

    /** @return array<int, int> */
    private static function reportedStatuses(): array
    {
        $reports = Report::query()->visible()->select(['id', 'spring_id', ...Report::CONDITION_COLUMNS])->get();
        $statuses = [];

        foreach ($reports->groupBy('spring_id') as $springId => $springReports) {
            $statuses[$springId] = self::statusFromReports($springReports);
        }

        return $statuses;
    }

    /** Matches Spring::getWaterScore()/isNotFound() while retaining report presence. */
    private static function statusFromReports(iterable $reports): int
    {
        $count = 0;
        $scoreCount = 0;
        $scoreSum = 0;
        $notFound = false;
        $found = false;

        foreach ($reports as $report) {
            $count++;
            $score = $report->getWaterScore();

            if ($score !== null) {
                $scoreCount++;
                $scoreSum += $score;
            }

            $notFound = $notFound || $report->state === \App\Enums\ReportState::NotFound;
            $found = $found || $report->confirmsSpringFound();
        }

        if ($notFound && ! $found) {
            return 4;
        }

        if ($count === 0) {
            return 0;
        }

        $score = $scoreCount === 0 ? null : $scoreSum / $scoreCount;

        return match (true) {
            $score !== null && $score >= Spring::WATER_SCORE_THRESHOLD => 2,
            $score !== null && $score <= -Spring::WATER_SCORE_THRESHOLD => 3,
            default => 1,
        };
    }
}
