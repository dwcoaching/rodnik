<?php

declare(strict_types=1);

namespace App\Services\SourceRaster;

use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

/**
 * One numeric snapshot (index.sqlite) updated in place, and one set of PNGs per
 * revision. A revision changes only when everything is redrawn (build or restyle);
 * a source edit rewrites just the tiles that contain it.
 */
class RasterPyramid
{
    private ?PDO $connection = null;

    public function manifest(): ?array
    {
        $path = $this->root().'/manifest.json';
        if (! is_file($path) || ! is_file($this->snapshotPath())) {
            return null;
        }
        $manifest = json_decode(file_get_contents($path), true);

        return is_array($manifest) && isset($manifest['revision'], $manifest['max_zoom']) ? $manifest : null;
    }

    /**
     * Whether visitors get the raster overview. Off until a superadmin turns it on,
     * so tiles can be built and tuned while the map keeps its vector overview.
     */
    public function enabled(): bool
    {
        return is_file($this->root().'/enabled');
    }

    public function setEnabled(bool $enabled): void
    {
        if ($enabled) {
            $this->atomic($this->root().'/enabled', gmdate('c')."\n");
        } else {
            @unlink($this->root().'/enabled');
        }
    }

    /** The style the published tiles were drawn with. */
    public function style(): array
    {
        return $this->manifest()['style'] ?? config('source_raster.style');
    }

    /** A style saved by an admin, waiting for the next refresh to redraw every tile. */
    public function pendingStyle(): ?array
    {
        $path = $this->root().'/style.pending.json';
        try {
            return is_file($path) ? RasterRenderer::normalizeStyle(json_decode(file_get_contents($path), true)) : null;
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    public function queueStyle(array $style): void
    {
        $this->atomic($this->root().'/style.pending.json', json_encode(RasterRenderer::normalizeStyle($style), JSON_THROW_ON_ERROR));
    }

    /** @return array{revision: ?string, generated_at: ?string, enabled: bool, pending: bool, busy: bool} */
    public function status(): array
    {
        $manifest = $this->manifest();

        return [
            'revision' => $manifest['revision'] ?? null,
            'generated_at' => $manifest['generated_at'] ?? null,
            'enabled' => $this->enabled(),
            'pending' => $this->pendingStyle() !== null,
            'busy' => $this->locked(fn () => false, true),
        ];
    }

    public function build(int $maxZoom = 10, bool $render = true, ?callable $progress = null): array
    {
        if ($maxZoom < 0 || $maxZoom > 10) {
            throw new InvalidArgumentException('Overview zoom must be between 0 and 10.');
        }

        return $this->locked(function () use ($maxZoom, $render, $progress) {
            $started = microtime(true);
            $revision = bin2hex(random_bytes(12));
            $staging = $this->snapshotPath().'.building';
            @unlink($staging);
            $pending = $this->pendingStyle();
            $style = $pending ?? $this->style();
            // Edits queued before this point are covered by the fresh count.
            $this->forgetQueue();
            try {
                $stats = RasterSnapshot::build($staging, $maxZoom, $progress);
                $stats += ['revision' => $revision, 'tiles' => 0, 'png_bytes' => 0, 'render_seconds' => 0];
                if ($render) {
                    $stats = array_replace($stats, $this->warm($revision, $style, RasterSnapshot::open($staging), $progress));
                }
                $stats['total_seconds'] = microtime(true) - $started;
                $stats['peak_memory_bytes'] = memory_get_peak_usage(true);
                // Edits made while this ran stay queued; refresh recounts their pixels.
                $this->connection = null;
                if (! rename($staging, $this->snapshotPath())) {
                    throw new RuntimeException('Could not publish the source overview snapshot.');
                }
                $this->publish($revision, $maxZoom, $style, $stats);
                if ($pending !== null) {
                    @unlink($this->root().'/style.pending.json');
                }

                return $stats;
            } catch (Throwable $exception) {
                @unlink($staging);
                File::deleteDirectory($this->tilesDirectory($revision));
                throw $exception;
            }
        });
    }

    /** Redraw every tile from the existing counts, with the given or current style. */
    public function restyle(bool $render = true, ?callable $progress = null, ?array $style = null): array
    {
        return $this->locked(fn () => $this->redraw($render, $progress, $style));
    }

    /** Path of an unfiltered tile. A missing tile of the current revision is drawn first. */
    public function tile(string $revision, int $z, int $x, int $y): string
    {
        $path = $this->tilesDirectory($revision).'/'.$z.'/'.$x.'/'.$y.'.png';
        if ($z < 0 || $x < 0 || $y < 0) {
            throw new InvalidArgumentException('Unknown source overview tile.');
        }
        if (is_file($path)) {
            return $path;
        }
        // Older revisions are served only from what was already drawn.
        if ($revision !== ($this->manifest()['revision'] ?? null)) {
            throw new InvalidArgumentException('Unknown source overview tile.');
        }
        $this->atomic($path, $this->png($z, $x, $y));

        return $path;
    }

    /** Draw one tile from the counts, optionally with a draft style. */
    public function png(int $z, int $x, int $y, ?array $style = null): string
    {
        $manifest = $this->manifest();
        if ($manifest === null || $z < 0 || $z > $manifest['max_zoom'] || $x < 0 || $y < 0
            || $x >= (1 << $z) || $y >= (1 << $z)) {
            throw new InvalidArgumentException('Unknown source overview tile.');
        }
        $cells = $this->cells($this->database(), $z, $x, $y);

        return app(RasterRenderer::class)->png($cells, $z, $style ?? $this->style());
    }

    /** Queue coordinates instead of redrawing tiles during user requests. */
    public function invalidate(float $longitude, float $latitude): void
    {
        if (RasterSnapshot::project($longitude, $latitude, 0) === null) {
            return;
        }
        $this->queueLock(function () use ($longitude, $latitude) {
            file_put_contents($this->root().'/dirty.jsonl', json_encode([$longitude, $latitude], JSON_THROW_ON_ERROR)."\n", FILE_APPEND);
        });
    }

    /** Recount the queued pixels from the database and redraw only the tiles that show them. */
    public function refresh(): array
    {
        return $this->locked(function () {
            $manifest = $this->manifest();
            if ($manifest === null) {
                // Nothing to update yet, and a build counts every source anyway.
                $this->forgetQueue();

                return ['changed' => 0, 'message' => 'Build the source overview first.'];
            }
            if (($style = $this->pendingStyle()) !== null) {
                $stats = $this->redraw(true, null, $style);
                @unlink($this->root().'/style.pending.json');

                return ['changed' => 0, 'restyled' => true] + $stats;
            }
            // Work left by a failed run may already be counted but not drawn.
            $replay = (int) @filesize($this->root().'/processing.jsonl') > 0;
            $coordinates = $this->queueLock(function () {
                $path = $this->root().'/dirty.jsonl';
                $inflight = $this->root().'/processing.jsonl';
                $lines = array_merge(
                    is_file($inflight) ? file($inflight, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [],
                    is_file($path) ? file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [],
                );
                $unique = [];
                foreach ($lines as $line) {
                    $coordinate = json_decode($line, true);
                    if (is_array($coordinate) && count($coordinate) === 2 && is_numeric($coordinate[0]) && is_numeric($coordinate[1])) {
                        $unique[$line] = $coordinate;
                    }
                }
                // Persist the claimed work before draining the live queue. A killed worker
                // leaves it here for replay; writes arriving during this run stay in dirty.
                $this->atomic($inflight, implode("\n", array_keys($unique)).($unique === [] ? '' : "\n"));
                file_put_contents($path, '');

                return array_values($unique);
            });
            if ($coordinates === []) {
                $this->acknowledge();

                return ['changed' => 0];
            }
            $started = microtime(true);
            $maxZoom = (int) $manifest['max_zoom'];
            $database = RasterSnapshot::open($this->snapshotPath(), true);
            $read = $database->prepare('SELECT type,status,n FROM cells WHERE z=? AND tile_x=? AND tile_y=? AND px=? AND py=?');
            $changes = $pixels = $seen = [];
            foreach ($coordinates as [$longitude, $latitude]) {
                $position = RasterSnapshot::project((float) $longitude, (float) $latitude, $maxZoom);
                if ($position === null || isset($seen[$position['x'].':'.$position['y']])) {
                    continue;
                }
                $seen[$position['x'].':'.$position['y']] = true;
                // Compare the stored counts of this finest pixel with the live database.
                $delta = [];
                $read->execute([$maxZoom, intdiv($position['x'], 512), intdiv($position['y'], 512), $position['x'] % 512, $position['y'] % 512]);
                foreach ($read as $cell) {
                    $delta[$cell['type'].':'.$cell['status']] = -$cell['n'];
                }
                foreach (RasterSnapshot::pointsNearCoordinates((float) $longitude, (float) $latitude, $maxZoom) as $point) {
                    if ($point['status'] !== 4) {
                        $key = $point['type'].':'.$point['status'];
                        $delta[$key] = ($delta[$key] ?? 0) + 1;
                    }
                }
                $delta = array_filter($delta);
                if ($delta !== []) {
                    $changes[] = [$position, $delta];
                }
                if ($delta !== [] || $replay) {
                    $pixels[] = $position;
                }
            }
            if ($changes !== []) {
                $add = $database->prepare('INSERT INTO cells (z,tile_x,tile_y,px,py,type,status,n) VALUES (?,?,?,?,?,?,?,?) ON CONFLICT(z,tile_x,tile_y,px,py,type,status) DO UPDATE SET n=n+excluded.n');
                $removeEmpty = $database->prepare('DELETE FROM cells WHERE z=? AND tile_x=? AND tile_y=? AND px=? AND py=? AND type=? AND status=? AND n<=0');
                $database->beginTransaction();
                try {
                    foreach ($changes as [$position, $delta]) {
                        for ($zoom = 0; $zoom <= $maxZoom; $zoom++) {
                            $px = $position['x'] >> ($maxZoom - $zoom);
                            $py = $position['y'] >> ($maxZoom - $zoom);
                            foreach ($delta as $key => $difference) {
                                [$type, $status] = array_map('intval', explode(':', $key));
                                $cell = [$zoom, intdiv($px, 512), intdiv($py, 512), $px % 512, $py % 512, $type, $status];
                                $add->execute([...$cell, $difference]);
                                if ($difference < 0) {
                                    $removeEmpty->execute($cell);
                                }
                            }
                        }
                    }
                    $database->commit();
                } catch (Throwable $exception) {
                    $database->rollBack();
                    throw $exception;
                }
            }
            $add = $removeEmpty = $read = $database = null;
            $this->connection = null;
            $tiles = [];
            foreach ($pixels as $position) {
                for ($zoom = 0; $zoom <= $maxZoom; $zoom++) {
                    $px = $position['x'] >> ($maxZoom - $zoom);
                    $py = $position['y'] >> ($maxZoom - $zoom);
                    $this->addFootprint($tiles, $zoom, intdiv($px, 512), intdiv($py, 512), $px % 512, $px % 512, $py % 512, $py % 512);
                }
            }
            // A failure here leaves processing.jsonl in place, so the next run redraws these.
            foreach ($tiles as [$zoom, $x, $y]) {
                $this->atomic($this->tilesDirectory($manifest['revision']).'/'.$zoom.'/'.$x.'/'.$y.'.png', $this->png($zoom, $x, $y));
            }
            if ($tiles !== []) {
                $this->atomic($this->root().'/manifest.json', json_encode(['updated_at' => gmdate('c')] + $manifest, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
            }
            $this->acknowledge();

            return ['changed' => count($changes), 'tiles' => count($tiles), 'total_seconds' => microtime(true) - $started];
        }, ['changed' => 0, 'message' => 'Another source overview task is running.']);
    }

    private function redraw(bool $render, ?callable $progress, ?array $style): array
    {
        $manifest = $this->manifest();
        if ($manifest === null) {
            throw new RuntimeException('Build the source overview before restyling it.');
        }
        $started = microtime(true);
        $style = RasterRenderer::normalizeStyle($style ?? $this->style());
        $revision = bin2hex(random_bytes(12));
        try {
            $stats = array_replace($manifest['statistics'] ?? [], ['revision' => $revision, 'tiles' => 0, 'png_bytes' => 0, 'render_seconds' => 0]);
            if ($render) {
                $stats = array_replace($stats, $this->warm($revision, $style, $this->database(), $progress));
            }
            $stats['total_seconds'] = microtime(true) - $started;
            $stats['peak_memory_bytes'] = memory_get_peak_usage(true);
            $this->publish($revision, (int) $manifest['max_zoom'], $style, $stats);

            return $stats;
        } catch (Throwable $exception) {
            File::deleteDirectory($this->tilesDirectory($revision));
            throw $exception;
        }
    }

    private function warm(string $revision, array $style, PDO $database, ?callable $progress = null): array
    {
        $started = microtime(true);
        $tiles = [];
        $statement = $database->query('SELECT z,tile_x,tile_y,MIN(px) AS min_x,MAX(px) AS max_x,MIN(py) AS min_y,MAX(py) AS max_y FROM cells GROUP BY z,tile_x,tile_y');
        foreach ($statement as $row) {
            $this->addFootprint($tiles, $row['z'], $row['tile_x'], $row['tile_y'], $row['min_x'], $row['max_x'], $row['min_y'], $row['max_y']);
        }
        $renderer = app(RasterRenderer::class);
        $bytes = 0;
        $count = 0;
        foreach ($tiles as [$zoom, $x, $y]) {
            $png = $renderer->png($this->cells($database, $zoom, $x, $y), $zoom, $style);
            $this->atomic($this->tilesDirectory($revision).'/'.$zoom.'/'.$x.'/'.$y.'.png', $png);
            $bytes += strlen($png);
            $count++;
            if ($count % 1000 === 0) {
                $progress && $progress('tiles', $count);
            }
        }
        $progress && $progress('tiles', $count);

        return ['tiles' => $count, 'png_bytes' => $bytes, 'render_seconds' => microtime(true) - $started];
    }

    private function cells(PDO $database, int $zoom, int $x, int $y): iterable
    {
        $count = 1 << $zoom;
        $statement = $database->prepare('SELECT px,py,status,SUM(n) AS n FROM cells WHERE z=? AND tile_x=? AND tile_y=? AND px>=? AND px<=? AND py>=? AND py<=? GROUP BY px,py,status');
        for ($dx = -1; $dx <= 1; $dx++) {
            for ($dy = -1; $dy <= 1; $dy++) {
                if ($y + $dy < 0 || $y + $dy >= $count) {
                    continue;
                }
                $statement->execute([$zoom, ($x + $dx + $count) % $count, $y + $dy,
                    $dx < 0 ? 512 - RasterRenderer::GUTTER : 0, $dx > 0 ? RasterRenderer::GUTTER : 511,
                    $dy < 0 ? 512 - RasterRenderer::GUTTER : 0, $dy > 0 ? RasterRenderer::GUTTER : 511]);
                foreach ($statement as $cell) {
                    yield ['px' => $cell['px'] + $dx * 512, 'py' => $cell['py'] + $dy * 512, 'status' => $cell['status'], 'n' => $cell['n']];
                }
            }
        }
    }

    private function addFootprint(array &$tiles, int $z, int $x, int $y, int $minX, int $maxX, int $minY, int $maxY): void
    {
        $xs = $ys = [0];
        if ($minX <= RasterRenderer::GUTTER) {
            $xs[] = -1;
        }
        if ($maxX >= 512 - RasterRenderer::GUTTER) {
            $xs[] = 1;
        }
        if ($minY <= RasterRenderer::GUTTER) {
            $ys[] = -1;
        }
        if ($maxY >= 512 - RasterRenderer::GUTTER) {
            $ys[] = 1;
        }
        $count = 1 << $z;
        foreach ($xs as $dx) {
            foreach ($ys as $dy) {
                if ($y + $dy >= 0 && $y + $dy < $count) {
                    $tx = ($x + $dx + $count) % $count;
                    $ty = $y + $dy;
                    $tiles[$z.'/'.$tx.'/'.$ty] = [$z, $tx, $ty];
                }
            }
        }
    }

    private function database(): PDO
    {
        return $this->connection ??= RasterSnapshot::open($this->snapshotPath());
    }

    private function publish(string $revision, int $maxZoom, array $style, array $stats): void
    {
        $previous = $this->manifest()['revision'] ?? null;
        $this->atomic($this->root().'/manifest.json', json_encode([
            'revision' => $revision, 'max_zoom' => $maxZoom, 'tile_size' => 512, 'tile_pixel_ratio' => 2,
            'previous_revision' => $previous,
            'url' => '/source-tiles/'.$revision.'/{z}/{x}/{y}.png',
            'generated_at' => gmdate('c'), 'updated_at' => gmdate('c'),
            'style' => $style, 'statistics' => $stats,
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
        try {
            // Maps still open on the previous revision keep working until the next redraw.
            foreach (glob($this->root().'/tiles/*', GLOB_ONLYDIR) ?: [] as $directory) {
                if (! in_array(basename($directory), [$revision, $previous], true)) {
                    File::deleteDirectory($directory);
                }
            }
        } catch (Throwable $exception) {
            // Cleanup must never roll back a successfully published manifest.
            report($exception);
        }
    }

    private function forgetQueue(): void
    {
        $this->queueLock(function () {
            @unlink($this->root().'/processing.jsonl');
            if (is_file($this->root().'/dirty.jsonl')) {
                file_put_contents($this->root().'/dirty.jsonl', '');
            }
        });
    }

    private function acknowledge(): void
    {
        try {
            $this->queueLock(function () {
                @unlink($this->root().'/processing.jsonl');
            });
        } catch (Throwable $exception) {
            // Publication already succeeded. Keep the work for an idempotent replay
            // rather than deleting the generation now referenced by the manifest.
            report($exception);
        }
    }

    private function root(): string
    {
        return config('source_raster.root');
    }

    private function snapshotPath(): string
    {
        return $this->root().'/index.sqlite';
    }

    private function tilesDirectory(string $revision): string
    {
        if (! preg_match('/\A[a-f0-9]{24}\z/', $revision)) {
            throw new InvalidArgumentException('Invalid source overview revision.');
        }

        return $this->root().'/tiles/'.$revision;
    }

    private function ensureDirectory(string $path): void
    {
        // Cold neighboring tiles can create the same directory simultaneously.
        if (! is_dir($path) && ! @mkdir($path, 0755, true) && ! is_dir($path)) {
            throw new RuntimeException('Could not create the source overview directory.');
        }
    }

    private function atomic(string $path, string $contents): void
    {
        $this->ensureDirectory(dirname($path));
        $temporary = $path.'.'.bin2hex(random_bytes(6)).'.tmp';
        if (file_put_contents($temporary, $contents) === false || ! rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException('Could not publish source overview file.');
        }
    }

    /** Run alone. When busy, return $whenBusy if one was given, otherwise fail. */
    private function locked(callable $callback, mixed $whenBusy = null): mixed
    {
        $this->ensureDirectory($this->root());
        $lock = fopen($this->root().'/build.lock', 'c');
        if (! flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            if (func_num_args() > 1) {
                return $whenBusy;
            }
            throw new RuntimeException('Another source overview build is already running.');
        }
        try {
            return $callback();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function queueLock(callable $callback): mixed
    {
        $this->ensureDirectory($this->root());
        $lock = fopen($this->root().'/queue.lock', 'c');
        flock($lock, LOCK_EX);
        try {
            return $callback();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
