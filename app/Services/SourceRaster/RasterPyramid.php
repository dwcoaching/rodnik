<?php

declare(strict_types=1);

namespace App\Services\SourceRaster;

use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

/** Immutable numeric snapshots and lazily cached PNGs; only manifest discovery is mutable. */
class RasterPyramid
{
    private array $connections = [];

    public function manifest(): ?array
    {
        $path = $this->root().'/manifest.json';
        if (! is_file($path)) {
            return null;
        }
        $manifest = json_decode(file_get_contents($path), true);

        return is_array($manifest) && isset($manifest['revision'], $manifest['max_zoom'])
            && is_file($this->directory($manifest['revision']).'/index.sqlite') ? $manifest : null;
    }

    public function build(int $maxZoom = 10, bool $render = true, ?callable $progress = null): array
    {
        if ($maxZoom < 0 || $maxZoom > 10) {
            throw new InvalidArgumentException('Overview zoom must be between 0 and 10.');
        }

        return $this->locked(function () use ($maxZoom, $render, $progress) {
            $started = microtime(true);
            $revision = bin2hex(random_bytes(12));
            $directory = $this->directory($revision);
            $this->ensureDirectory($directory);
            try {
                $stats = RasterSnapshot::build($directory.'/index.sqlite', $maxZoom, $progress);
                $this->atomic($directory.'/metadata.json', json_encode($stats, JSON_THROW_ON_ERROR));
                $stats += ['revision' => $revision, 'tiles' => 0, 'png_bytes' => 0, 'render_seconds' => 0];
                if ($render) {
                    $stats = array_replace($stats, $this->warm($revision, $progress));
                }
                $stats['total_seconds'] = microtime(true) - $started;
                $stats['peak_memory_bytes'] = memory_get_peak_usage(true);
                $this->publish($revision, $maxZoom, $stats);

                return $stats;
            } catch (Throwable $exception) {
                unset($this->connections[$revision]);
                File::deleteDirectory($directory);
                throw $exception;
            }
        });
    }

    /** Repaint immutable numeric data under fresh URLs without extracting sources again. */
    public function restyle(bool $render = true, ?callable $progress = null): array
    {
        return $this->locked(function () use ($render, $progress) {
            $manifest = $this->manifest();
            if ($manifest === null) {
                throw new RuntimeException('Build the source overview before restyling it.');
            }

            $started = microtime(true);
            $previous = $this->directory($manifest['revision']);
            $revision = bin2hex(random_bytes(12));
            $directory = $this->directory($revision);
            $this->ensureDirectory($directory);

            try {
                // Snapshots are never modified after publication. Sharing their inode
                // is safe; refresh() copies it before applying any source changes.
                if (! @link($previous.'/index.sqlite', $directory.'/index.sqlite')
                    && ! copy($previous.'/index.sqlite', $directory.'/index.sqlite')) {
                    throw new RuntimeException('Could not reuse the source overview snapshot.');
                }

                $metadata = json_decode(file_get_contents($previous.'/metadata.json'), true, 512, JSON_THROW_ON_ERROR);
                $this->atomic($directory.'/metadata.json', json_encode($metadata, JSON_THROW_ON_ERROR));
                $stats = array_replace($metadata, [
                    'revision' => $revision,
                    'reused_snapshot' => $manifest['revision'],
                    'max_zoom' => (int) $manifest['max_zoom'],
                    'seconds' => 0,
                    'bytes' => (int) filesize($directory.'/index.sqlite'),
                    'tiles' => 0,
                    'png_bytes' => 0,
                    'render_seconds' => 0,
                ]);
                if ($render) {
                    $stats = array_replace($stats, $this->warm($revision, $progress));
                }
                $stats['total_seconds'] = microtime(true) - $started;
                $stats['peak_memory_bytes'] = memory_get_peak_usage(true);
                $this->publish($revision, (int) $manifest['max_zoom'], $stats);

                return $stats;
            } catch (Throwable $exception) {
                unset($this->connections[$revision]);
                File::deleteDirectory($directory);
                throw $exception;
            }
        });
    }

    /** A cache miss draws one small tile from numeric counts; it never rebuilds the dataset. */
    public function tile(string $revision, int $z, int $x, int $y, int $types = 63, bool $reportsOnly = false, ?array $polygon = null): string
    {
        $directory = $this->directory($revision);
        $metadata = is_file($directory.'/metadata.json') ? json_decode(file_get_contents($directory.'/metadata.json'), true) : null;
        if ($metadata === null || $z < 0 || $z > $metadata['max_zoom'] || $x < 0 || $y < 0
            || $x >= (1 << $z) || $y >= (1 << $z) || $types < 0 || $types > 63) {
            throw new InvalidArgumentException('Unknown source overview tile.');
        }
        $variant = $types.'-'.(int) $reportsOnly.($polygon === null ? '' : '-'.hash('sha256', json_encode($polygon, JSON_THROW_ON_ERROR)));
        $path = $directory.'/tiles/'.$variant.'/'.$z.'/'.$x.'/'.$y.'.png';
        if (is_file($path)) {
            return $path;
        }
        $database = $this->database($revision);
        $cells = $types === 0 ? [] : ($polygon === null
            ? $this->cells($database, $z, $x, $y, $types, $reportsOnly)
            : $this->polygonCells($database, (int) $metadata['max_zoom'], $z, $x, $y, $types, $reportsOnly, $polygon));
        $this->atomic($path, app(RasterRenderer::class)->png($cells, $z));

        return $path;
    }

    /** Queue coordinates instead of deleting published PNGs during user requests. */
    public function invalidate(float $longitude, float $latitude): void
    {
        if (RasterSnapshot::project($longitude, $latitude, 0) === null) {
            return;
        }
        $this->queueLock(function () use ($longitude, $latitude) {
            file_put_contents($this->root().'/dirty.jsonl', json_encode([$longitude, $latitude], JSON_THROW_ON_ERROR)."\n", FILE_APPEND);
        });
    }

    /** Copy-on-publish means old URLs continue to describe exactly the old data. */
    public function refresh(): array
    {
        return $this->locked(function () {
            $manifest = $this->manifest();
            if ($manifest === null) {
                return ['changed' => 0, 'message' => 'Build the source overview first.'];
            }
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
                return ['changed' => 0];
            }
            $started = microtime(true);
            $maxZoom = (int) $manifest['max_zoom'];
            $revision = bin2hex(random_bytes(12));
            $directory = $this->directory($revision);
            $this->ensureDirectory($directory);
            $database = null;
            try {
                // Re-read each affected finest pixel once, including removals and old move locations.
                $previous = $this->database($manifest['revision']);
                $lookup = $previous->prepare('SELECT * FROM points WHERE x = ? AND y = ?');
                $lookupId = $previous->prepare('SELECT * FROM points WHERE id = ?');
                $before = $after = $seen = [];
                foreach ($coordinates as [$longitude, $latitude]) {
                    $position = RasterSnapshot::project((float) $longitude, (float) $latitude, $maxZoom);
                    if ($position === null || isset($seen[$position['x'].':'.$position['y']])) {
                        continue;
                    }
                    $seen[$position['x'].':'.$position['y']] = true;
                    $lookup->execute([$position['x'], $position['y']]);
                    foreach ($lookup as $point) {
                        $before[$point['id']] = $point;
                    }
                    foreach (RasterSnapshot::pointsNearCoordinates((float) $longitude, (float) $latitude, $maxZoom) as $point) {
                        $after[$point['id']] = $point;
                        if (! isset($before[$point['id']])) {
                            $lookupId->execute([$point['id']]);
                            if ($old = $lookupId->fetch()) {
                                $before[$point['id']] = $old;
                            }
                        }
                    }
                }
                $changes = [];
                foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $id) {
                    if (($before[$id] ?? null) !== ($after[$id] ?? null)) {
                        $changes[$id] = [$before[$id] ?? null, $after[$id] ?? null];
                    }
                }
                if ($changes === []) {
                    File::deleteDirectory($directory);
                    $this->acknowledge();

                    return ['changed' => 0, 'total_seconds' => microtime(true) - $started];
                }
                if (! copy($this->directory($manifest['revision']).'/index.sqlite', $directory.'/index.sqlite')) {
                    throw new RuntimeException('Could not copy the source overview snapshot.');
                }
                $database = new PDO('sqlite:'.$directory.'/index.sqlite', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $database->exec('PRAGMA journal_mode=OFF; PRAGMA synchronous=OFF');
                $database->beginTransaction();
                $removePoint = $database->prepare('DELETE FROM points WHERE id = ?');
                $addPoint = $database->prepare('INSERT INTO points (id,x,y,type,status,longitude,latitude) VALUES (?,?,?,?,?,?,?)');
                $removeCell = $database->prepare('UPDATE cells SET n=n-1 WHERE z=? AND tile_x=? AND tile_y=? AND px=? AND py=? AND type=? AND status=?');
                $removeEmptyCell = $database->prepare('DELETE FROM cells WHERE z=? AND tile_x=? AND tile_y=? AND px=? AND py=? AND type=? AND status=? AND n<=0');
                $addCell = $database->prepare('INSERT INTO cells (z,tile_x,tile_y,px,py,type,status,n) VALUES (?,?,?,?,?,?,?,1) ON CONFLICT(z,tile_x,tile_y,px,py,type,status) DO UPDATE SET n=n+1');
                $dirtyTiles = [];
                $sourceCount = (int) $previous->query("SELECT value FROM metadata WHERE key='source_count'")->fetchColumn();
                foreach ($changes as $id => [$old, $new]) {
                    $sourceCount += (int) ($new !== null) - (int) ($old !== null);
                    $removePoint->execute([$id]);
                    foreach ([[$old, $removeCell], [$new, $addCell]] as [$point, $statement]) {
                        if ($point === null) {
                            continue;
                        }
                        for ($zoom = 0; $zoom <= $maxZoom; $zoom++) {
                            $px = $point['x'] >> ($maxZoom - $zoom);
                            $py = $point['y'] >> ($maxZoom - $zoom);
                            if ($point['status'] !== 4) {
                                $key = [$zoom, intdiv($px, 512), intdiv($py, 512), $px % 512, $py % 512, $point['type'], $point['status']];
                                $statement->execute($key);
                                if ($statement === $removeCell) {
                                    $removeEmptyCell->execute($key);
                                }
                            }
                            $this->addFootprint($dirtyTiles, $zoom, intdiv($px, 512), intdiv($py, 512), $px % 512, $px % 512, $py % 512, $py % 512);
                        }
                    }
                    if ($new !== null) {
                        $addPoint->execute([$new['id'], $new['x'], $new['y'], $new['type'], $new['status'], $new['longitude'], $new['latitude']]);
                    }
                }
                $database->exec("UPDATE metadata SET value='{$sourceCount}' WHERE key='source_count'");
                $database->commit();
                $database = null;
                $stats = ['source_count' => $sourceCount, 'max_zoom' => $maxZoom, 'changed' => count($changes), 'tiles' => count($dirtyTiles)];
                $this->atomic($directory.'/metadata.json', json_encode($stats, JSON_THROW_ON_ERROR));
                foreach ($dirtyTiles as [$zoom, $x, $y]) {
                    $this->tile($revision, $zoom, $x, $y);
                }
                $stats['total_seconds'] = microtime(true) - $started;
                $this->publish($revision, $maxZoom, $stats);
                $this->acknowledge();

                return $stats + ['revision' => $revision];
            } catch (Throwable $exception) {
                if ($database?->inTransaction()) {
                    $database->rollBack();
                }
                $database = null;
                unset($this->connections[$revision]);
                File::deleteDirectory($directory);
                // processing.jsonl remains durable until publication succeeds.
                throw $exception;
            }
        });
    }

    public function warm(string $revision, ?callable $progress = null): array
    {
        $started = microtime(true);
        $tiles = [];
        $statement = $this->database($revision)->query('SELECT z,tile_x,tile_y,MIN(px) AS min_x,MAX(px) AS max_x,MIN(py) AS min_y,MAX(py) AS max_y FROM cells GROUP BY z,tile_x,tile_y');
        foreach ($statement as $row) {
            $this->addFootprint($tiles, $row['z'], $row['tile_x'], $row['tile_y'], $row['min_x'], $row['max_x'], $row['min_y'], $row['max_y']);
        }
        $bytes = 0;
        $count = 0;
        foreach ($tiles as [$zoom, $x, $y]) {
            $path = $this->tile($revision, $zoom, $x, $y);
            $bytes += filesize($path);
            $count++;
            if ($count % 1000 === 0) {
                $progress && $progress('tiles', $count);
            }
        }
        $progress && $progress('tiles', $count);

        return ['tiles' => $count, 'png_bytes' => $bytes, 'render_seconds' => microtime(true) - $started];
    }

    private function cells(PDO $database, int $zoom, int $x, int $y, int $types, bool $reports): iterable
    {
        $count = 1 << $zoom;
        $statement = $database->prepare('SELECT px,py,status,SUM(n) AS n FROM cells WHERE z=? AND tile_x=? AND tile_y=? AND (type & ?)<>0 AND status>=? AND px>=? AND px<=? AND py>=? AND py<=? GROUP BY px,py,status');
        for ($dx = -1; $dx <= 1; $dx++) {
            for ($dy = -1; $dy <= 1; $dy++) {
                if ($y + $dy < 0 || $y + $dy >= $count) {
                    continue;
                }
                $statement->execute([$zoom, ($x + $dx + $count) % $count, $y + $dy, $types, $reports ? 1 : 0,
                    $dx < 0 ? 512 - RasterRenderer::GUTTER : 0, $dx > 0 ? RasterRenderer::GUTTER : 511,
                    $dy < 0 ? 512 - RasterRenderer::GUTTER : 0, $dy > 0 ? RasterRenderer::GUTTER : 511]);
                foreach ($statement as $cell) {
                    yield ['px' => $cell['px'] + $dx * 512, 'py' => $cell['py'] + $dy * 512, 'status' => $cell['status'], 'n' => $cell['n']];
                }
            }
        }
    }

    private function polygonCells(PDO $database, int $maxZoom, int $zoom, int $x, int $y, int $types, bool $reports, array $polygon): iterable
    {
        $factor = 1 << ($maxZoom - $zoom);
        $world = 512 * (1 << $maxZoom);
        $statement = $database->prepare('SELECT id,x,y,status,longitude,latitude FROM points WHERE x>=? AND x<? AND y>=? AND y<? AND (type & ?)<>0 AND status>=? AND status<4');
        $polygons = $this->preparePolygons($polygon);
        $deduplicate = count($polygons) > 1;
        $width = 512 + 2 * RasterRenderer::GUTTER;
        $counts = [];

        // Query each component's intersection with the tile, rather than scanning
        // every source worldwide for a narrow route displayed at world zoom.
        foreach ([-$world, 0, $world] as $wrap) {
            $seen = [];
            foreach ($polygons as $component) {
                [$west, $south, $east, $north] = $component['bbox'];
                $northPixel = RasterSnapshot::project(0, $north, $maxZoom)['y'];
                $southPixel = RasterSnapshot::project(0, $south, $maxZoom)['y'] + 1;
                $ranges = [[
                    max(0, (int) floor(($west + 180) / 360 * $world)),
                    min($world, (int) floor(($east + 180) / 360 * $world) + 1),
                ]];
                // Snapshot longitudes use -180 for both representations of the
                // antimeridian; an exterior edge at +180 must include those points.
                if ($east === 180.0 && $west > -180.0) {
                    $ranges[] = [0, 1];
                }
                foreach ($ranges as [$left, $right]) {
                    $bounds = [
                        max(0, $left, ($x * 512 - RasterRenderer::GUTTER) * $factor + $wrap),
                        min($world, $right, (($x + 1) * 512 + RasterRenderer::GUTTER) * $factor + $wrap),
                        max(0, $northPixel, ($y * 512 - RasterRenderer::GUTTER) * $factor),
                        min($world, $southPixel, (($y + 1) * 512 + RasterRenderer::GUTTER) * $factor),
                    ];
                    if ($bounds[0] >= $bounds[1] || $bounds[2] >= $bounds[3]) {
                        continue;
                    }
                    $statement->execute([...$bounds, $types, $reports ? 1 : 0]);
                    foreach ($statement as $point) {
                        if ($deduplicate && isset($seen[$point['id']])) {
                            continue;
                        }
                        // Test original coordinates, not the painted pixel. Two sources
                        // in the same pixel may lie on opposite sides of a route buffer.
                        if ($this->inside($point['longitude'], $point['latitude'], [$component])) {
                            if ($deduplicate) {
                                $seen[$point['id']] = true;
                            }
                            $px = (int) floor(($point['x'] - $wrap) / $factor) - $x * 512;
                            $py = intdiv($point['y'], $factor) - $y * 512;
                            $key = (($py + RasterRenderer::GUTTER) * $width + $px + RasterRenderer::GUTTER) * 4 + $point['status'];
                            $counts[$key] = ($counts[$key] ?? 0) + 1;
                        }
                    }
                }
            }
        }
        // Scalar counters bound memory by tile pixels. Paint each occupied pixel
        // once so a route changes selection without changing the density scale.
        foreach ($counts as $key => $count) {
            $pixel = intdiv($key, 4);
            yield ['px' => $pixel % $width - RasterRenderer::GUTTER, 'py' => intdiv($pixel, $width) - RasterRenderer::GUTTER, 'status' => $key % 4, 'n' => $count];
        }
    }

    /** Prepare bounds and a latitude edge index once per geometry, not per source. */
    private function preparePolygons(array $geometry): array
    {
        if (($geometry['type'] ?? '') === 'Feature') {
            $geometry = $geometry['geometry'];
        }
        $polygons = match ($geometry['type'] ?? '') {
            'Polygon' => [$geometry['coordinates']],
            'MultiPolygon' => $geometry['coordinates'],
            default => throw new InvalidArgumentException('Expected a source overview Polygon or MultiPolygon.'),
        };
        $prepared = [];
        foreach ($polygons as $rings) {
            if ($rings === []) {
                continue;
            }
            $rings = array_map($this->prepareRing(...), $rings);
            $prepared[] = ['bbox' => $rings[0]['bbox'], 'rings' => $rings];
        }

        return $prepared;
    }

    private function prepareRing(array $points): array
    {
        if (count($points) < 4) {
            throw new InvalidArgumentException('A source overview polygon ring requires at least four positions.');
        }
        $west = $east = (float) $points[0][0];
        $south = $north = (float) $points[0][1];
        foreach ($points as [$longitude, $latitude]) {
            $west = min($west, $longitude);
            $east = max($east, $longitude);
            $south = min($south, $latitude);
            $north = max($north, $latitude);
        }
        $binCount = min(256, max(1, (int) ceil(sqrt(count($points)))));
        $scale = $north > $south ? $binCount / ($north - $south) : 0.0;
        $bins = array_fill(0, $binCount, []);
        $wide = [];
        for ($index = 1, $count = count($points); $index < $count; $index++) {
            [$xi, $yi] = $points[$index - 1];
            [$xj, $yj] = $points[$index];
            $edge = [(float) $xi, (float) $yi, (float) $xj, (float) $yj];
            $first = min($binCount - 1, max(0, (int) floor((min($yi, $yj) - $south) * $scale)));
            $last = min($binCount - 1, max(0, (int) floor((max($yi, $yj) - $south) * $scale)));
            // A few long edges need no copies in hundreds of bins. This bounds
            // preparation memory even for geometries with large vertical spans.
            if ($last - $first > 8) {
                $wide[] = $edge;
            } else {
                for ($bin = $first; $bin <= $last; $bin++) {
                    $bins[$bin][] = $edge;
                }
            }
        }

        return ['bbox' => [(float) $west, (float) $south, (float) $east, (float) $north], 'bins' => $bins, 'wide' => $wide, 'scale' => $scale];
    }

    private function inside(float $x, float $y, array $polygons): bool
    {
        foreach ($polygons as $polygon) {
            foreach ($x === -180.0 ? [-180.0, 180.0] : [$x] as $longitude) {
                $outer = $this->insideRing($longitude, $y, $polygon['rings'][0]);
                if ($outer === -1) {
                    continue;
                }
                if ($outer === 0) {
                    return true;
                }
                foreach (array_slice($polygon['rings'], 1) as $hole) {
                    $inner = $this->insideRing($longitude, $y, $hole);
                    if ($inner === 0) {
                        return true;
                    }
                    if ($inner === 1) {
                        continue 2;
                    }
                }

                return true;
            }
        }

        return false;
    }

    /** -1 outside, 0 boundary, 1 inside; hole boundaries belong to the polygon. */
    private function insideRing(float $x, float $y, array $ring): int
    {
        [$west, $south, $east, $north] = $ring['bbox'];
        if ($x < $west || $x > $east || $y < $south || $y > $north) {
            return -1;
        }
        $bin = min(count($ring['bins']) - 1, max(0, (int) floor(($y - $south) * $ring['scale'])));
        $inside = false;
        foreach ([$ring['bins'][$bin], $ring['wide']] as $edges) {
            foreach ($edges as [$xi, $yi, $xj, $yj]) {
                if ($y < min($yi, $yj) || $y > max($yi, $yj) || $x > max($xi, $xj)) {
                    continue;
                }
                $cross = ($x - $xi) * ($yj - $yi) - ($y - $yi) * ($xj - $xi);
                if ($cross === 0.0 && $x >= min($xi, $xj) && $x <= max($xi, $xj)) {
                    return 0;
                }
                if (($yi > $y) !== ($yj > $y) && $x < ($xj - $xi) * ($y - $yi) / ($yj - $yi) + $xi) {
                    $inside = ! $inside;
                }
            }
        }

        return $inside ? 1 : -1;
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

    private function database(string $revision): PDO
    {
        return $this->connections[$revision] ??= RasterSnapshot::open($this->directory($revision).'/index.sqlite');
    }

    private function publish(string $revision, int $maxZoom, array $stats): void
    {
        $previous = $this->manifest()['revision'] ?? null;
        $this->atomic($this->root().'/manifest.json', json_encode([
            'revision' => $revision, 'max_zoom' => $maxZoom, 'tile_size' => 512, 'tile_pixel_ratio' => 2,
            'previous_revision' => $previous,
            'url' => '/source-tiles/'.$revision.'/{z}/{x}/{y}.png',
            'generated_at' => gmdate('c'), 'statistics' => $stats,
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
        try {
            $this->prune($revision, $previous);
        } catch (Throwable $exception) {
            // Cleanup must never roll back a successfully published manifest.
            report($exception);
        }
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

    private function prune(string $active, ?string $previous): void
    {
        $generations = glob($this->root().'/generations/*', GLOB_ONLYDIR) ?: [];
        usort($generations, fn ($a, $b) => filemtime($b) <=> filemtime($a));
        $cutoff = time() - max(1, (int) config('source_raster.retention_hours', 24)) * 3600;
        $grace = time() - max(1, (int) config('source_raster.retention_grace_minutes', 10)) * 60;
        $maximum = max(2, (int) config('source_raster.max_generations', 6));
        foreach (array_slice($generations, 2) as $index => $directory) {
            $revision = basename($directory);
            $expired = filemtime($directory) < $cutoff || ($index + 2 >= $maximum && filemtime($directory) < $grace);
            if ($revision !== $active && $revision !== $previous && $expired && preg_match('/\A[a-f0-9]{24}\z/', $revision)) {
                unset($this->connections[$revision]);
                File::deleteDirectory($directory);
            }
        }
    }

    private function root(): string
    {
        return config('source_raster.root');
    }

    private function directory(string $revision): string
    {
        if (! preg_match('/\A[a-f0-9]{24}\z/', $revision)) {
            throw new InvalidArgumentException('Invalid source overview revision.');
        }

        return $this->root().'/generations/'.$revision;
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

    private function locked(callable $callback): mixed
    {
        $this->ensureDirectory($this->root());
        $lock = fopen($this->root().'/build.lock', 'c');
        if (! flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
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
