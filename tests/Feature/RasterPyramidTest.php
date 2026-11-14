<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Report;
use App\Models\Spring;
use App\Services\SourceRaster\RasterPyramid;
use App\Services\SourceRaster\RasterRenderer;
use App\Services\SourceRaster\RasterSnapshot;
use Closure;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

final class RasterPyramidTest extends TestCase
{
    private string $rasterRoot;

    private RasterPyramid $pyramid;

    private string $revision;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rasterRoot = sys_get_temp_dir().'/rodnik-pyramid-'.bin2hex(random_bytes(8));
        config(['source_raster.root' => $this->rasterRoot]);
        $this->pyramid = new RasterPyramid;
        $this->revision = str_repeat('a', 24);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->rasterRoot);
        parent::tearDown();
    }

    public function test_real_pngs_preserve_condition_colors_and_obey_type_and_report_filters(): void
    {
        $points = [];

        foreach ([0, 1, 2, 3, 4] as $status) {
            $points[] = $this->point($status + 1, 1200 + $status * 40, 1400, 3, 1 << $status, $status);
        }

        $this->publishFixture($points, 3);
        $full = $this->pyramid->tile($this->revision, 3, 2, 2);
        self::assertSame([512, 512, IMAGETYPE_PNG], array_slice(getimagesize($full), 0, 3));

        foreach ([0, 1, 2, 3] as $status) {
            $this->assertPixelColor($full, 176 + $status * 40, 376, RasterRenderer::COLORS[$status]);
        }

        $this->assertTransparent($full, 336, 376); // Not-found sources remain absent.
        $reports = $this->pyramid->tile($this->revision, 3, 2, 2, 63, true);
        $this->assertTransparent($reports, 176, 376);
        $this->assertPixelColor($reports, 216, 376, RasterRenderer::COLORS[1]);
        $this->assertPixelColor($reports, 256, 376, RasterRenderer::COLORS[2]);
        $types = $this->pyramid->tile($this->revision, 3, 2, 2, 1 | 4);
        $this->assertPixelColor($types, 176, 376, RasterRenderer::COLORS[0]);
        $this->assertPixelColor($types, 256, 376, RasterRenderer::COLORS[2]);
        $this->assertTransparent($types, 216, 376);
        $this->assertTransparent($types, 296, 376);
        $empty = $this->pyramid->tile($this->revision, 3, 2, 2, 0);
        self::assertSame([], $this->paintedColors($empty));
        self::assertNotSame($full, $reports);
        self::assertSame($full, $this->pyramid->tile($this->revision, 3, 2, 2));
    }

    public function test_marks_cross_tile_seams_and_wrap_the_dateline_without_disappearing(): void
    {
        $this->publishFixture([
            $this->point(1, 511, 300, 1, 1, 2),
            $this->point(2, 200, 511, 1, 1, 1),
            $this->point(3, 0, 100, 1, 1, 3),
        ], 1);
        $topLeft = $this->pyramid->tile($this->revision, 1, 0, 0);
        $topRight = $this->pyramid->tile($this->revision, 1, 1, 0);
        $bottomLeft = $this->pyramid->tile($this->revision, 1, 0, 1);
        $this->assertPixelColor($topLeft, 511, 300, RasterRenderer::COLORS[2]);
        $this->assertPixelColor($topRight, 0, 300, RasterRenderer::COLORS[2]);
        $this->assertPixelColor($topLeft, 200, 511, RasterRenderer::COLORS[1]);
        $this->assertPixelColor($bottomLeft, 200, 0, RasterRenderer::COLORS[1]);
        $this->assertPixelColor($topLeft, 0, 100, RasterRenderer::COLORS[3]);
        $this->assertPixelColor($topRight, 511, 100, RasterRenderer::COLORS[3]);
        $stats = $this->pyramid->warm($this->revision);
        self::assertGreaterThanOrEqual(4, $stats['tiles']);
        self::assertGreaterThan(0, $stats['png_bytes']);
    }

    public function test_high_density_adds_ink_without_expanding_into_large_overlapping_discs(): void
    {
        $points = [$this->point(1, 100, 100, 0)];

        for ($id = 2; $id <= 257; $id++) {
            $points[] = $this->point($id, 300, 300, 0);
        }

        $this->publishFixture($points, 0);
        $tile = $this->pyramid->tile($this->revision, 0, 0, 0);
        $single = $this->pixel($tile, 100, 100);
        $dense = $this->pixel($tile, 300, 300);
        self::assertLessThan($single['alpha'], $dense['alpha']);
        $this->assertTransparent($tile, 103, 100);
        $this->assertTransparent($tile, 303, 300);
        $this->assertTransparent($tile, 297, 300);
    }

    public function test_polygon_filter_checks_original_coordinates_when_sources_share_a_painted_pixel(): void
    {
        $green = $this->point(1, 1000, 1000, 2, 1, 2);
        $red = [...$green, 'id' => 2, 'status' => 3, 'longitude' => $green['longitude'] + 0.02];
        self::assertSame(RasterSnapshot::project($green['longitude'], $green['latitude'], 2), RasterSnapshot::project($red['longitude'], $red['latitude'], 2));
        $this->publishFixture([$green, $red], 2);
        $full = $this->pyramid->tile($this->revision, 2, 1, 1);
        $this->assertContainsColor($full, RasterRenderer::COLORS[2]);
        $this->assertContainsColor($full, RasterRenderer::COLORS[3]);
        $polygon = $this->rectangle($green['longitude'] - 0.005, $green['latitude'] - 0.005, $green['longitude'] + 0.005, $green['latitude'] + 0.005);
        $filtered = $this->pyramid->tile($this->revision, 2, 1, 1, 63, false, $polygon);
        $this->assertContainsColor($filtered, RasterRenderer::COLORS[2]);
        $this->assertLacksColor($filtered, RasterRenderer::COLORS[3]);
    }

    public function test_polygon_holes_exclude_only_their_interior_even_when_sources_share_a_pixel(): void
    {
        $green = $this->point(1, 1000, 1000, 2, 1, 2);
        $red = [...$green, 'id' => 2, 'status' => 3, 'longitude' => $green['longitude'] + 0.02];
        $this->publishFixture([$green, $red], 2);
        $polygon = $this->rectangle($green['longitude'] - 0.05, $green['latitude'] - 0.05, $green['longitude'] + 0.05, $green['latitude'] + 0.05);
        $polygon['coordinates'][] = $this->rectangle($red['longitude'] - 0.005, $red['latitude'] - 0.005, $red['longitude'] + 0.005, $red['latitude'] + 0.005)['coordinates'][0];
        $tile = $this->pyramid->tile($this->revision, 2, 1, 1, 63, false, $polygon);
        $this->assertContainsColor($tile, RasterRenderer::COLORS[2]);
        $this->assertLacksColor($tile, RasterRenderer::COLORS[3]);
    }

    public function test_polygon_outer_and_hole_boundaries_match_the_source_route_filter(): void
    {
        $points = [];

        foreach ([[-10, 0, 2], [2, 0, 3], [0, 0, 1], [20, 0, 0]] as $index => [$longitude, $latitude, $status]) {
            $points[] = ['id' => $index + 1, ...RasterSnapshot::project($longitude, $latitude, 0), 'longitude' => $longitude, 'latitude' => $latitude, 'type' => 1, 'status' => $status];
        }

        $this->publishFixture($points, 0);
        $polygon = $this->rectangle(-10, -10, 10, 10);
        $polygon['coordinates'][] = $this->rectangle(-2, -2, 2, 2)['coordinates'][0];
        $tile = $this->pyramid->tile($this->revision, 0, 0, 0, 63, false, $polygon);
        $this->assertContainsColor($tile, RasterRenderer::COLORS[2]);
        $this->assertContainsColor($tile, RasterRenderer::COLORS[3]);
        $this->assertLacksColor($tile, RasterRenderer::COLORS[1]);
        $this->assertLacksColor($tile, RasterRenderer::COLORS[0]);
    }

    public function test_feature_multipolygons_preserve_concave_gaps_and_separate_components(): void
    {
        $this->visibleWorldBlue();
        $points = [];

        foreach ([[-55, 10, 2], [-40, -5, 3], [-40, 10, 1], [35, 35, 0]] as $index => [$longitude, $latitude, $status]) {
            $points[] = ['id' => $index + 1, ...RasterSnapshot::project($longitude, $latitude, 0), 'longitude' => $longitude, 'latitude' => $latitude, 'type' => 1, 'status' => $status];
        }

        $this->publishFixture($points, 0);
        $geometry = ['type' => 'Feature', 'properties' => [], 'geometry' => [
            'type' => 'MultiPolygon', 'coordinates' => [
                [[[-60, -10], [-30, -10], [-30, 0], [-50, 0], [-50, 20], [-60, 20], [-60, -10]]],
                $this->rectangle(30, 30, 40, 40)['coordinates'],
            ],
        ]];
        $tile = $this->pyramid->tile($this->revision, 0, 0, 0, 63, false, $geometry);
        $this->assertContainsColor($tile, RasterRenderer::COLORS[2]);
        $this->assertContainsColor($tile, RasterRenderer::COLORS[3]);
        $this->assertContainsColor($tile, RasterRenderer::COLORS[0]);
        $this->assertLacksColor($tile, RasterRenderer::COLORS[1]);
        $reported = $this->pyramid->tile($this->revision, 0, 0, 0, 63, true, $geometry);
        $this->assertContainsColor($reported, RasterRenderer::COLORS[2]);
        $this->assertLacksColor($reported, RasterRenderer::COLORS[0]);
    }

    public function test_polygon_eastern_dateline_boundary_includes_normalized_sources_and_wraps_the_mark(): void
    {
        $point = ['id' => 1, ...RasterSnapshot::project(-180, 20, 1), 'longitude' => -180.0, 'latitude' => 20.0, 'type' => 1, 'status' => 2];
        $this->publishFixture([$point], 1);
        $geometry = $this->rectangle(170, 10, 180, 30);
        $west = $this->pyramid->tile($this->revision, 1, 0, 0, 63, false, $geometry);
        $east = $this->pyramid->tile($this->revision, 1, 1, 0, 63, false, $geometry);
        $this->assertPixelColor($west, 0, $point['y'], RasterRenderer::COLORS[2]);
        $this->assertPixelColor($east, 511, $point['y'], RasterRenderer::COLORS[2]);
    }

    public function test_refresh_publishes_additions_conditions_moves_and_removals_without_mutating_old_snapshots(): void
    {
        $this->visibleWorldBlue();
        $this->publishFixture([], 10);
        $oldSnapshot = $this->snapshotPath($this->revision);
        $oldHash = hash_file('sha256', $oldSnapshot);
        $oldTile = $this->pyramid->tile($this->revision, 0, 0, 0);
        $oldTileHash = hash_file('sha256', $oldTile);
        $spring = Spring::factory()->create(['longitude' => 139.123456, 'latitude' => -47.654321, 'type' => 'Spring']);
        $this->pyramid->invalidate($spring->longitude, $spring->latitude);
        $this->pyramid->invalidate($spring->longitude, $spring->latitude);
        $stats = $this->pyramid->refresh();
        self::assertSame(1, $stats['changed']);
        self::assertSame(1, $stats['source_count']);
        $revision = $stats['revision'];
        self::assertNotSame($this->revision, $revision);
        self::assertSame($revision, $this->pyramid->manifest()['revision']);
        self::assertSame(0, $this->snapshotPoint($revision, $spring->id)['status']);
        self::assertSame($oldHash, hash_file('sha256', $oldSnapshot));
        self::assertSame($oldTileHash, hash_file('sha256', $this->pyramid->tile($this->revision, 0, 0, 0)));
        self::assertSame([], $this->paintedColors($oldTile));
        $this->assertContainsColor($this->pyramid->tile($revision, 0, 0, 0), RasterRenderer::COLORS[0]);

        $report = Report::factory()->for($spring)->create(['quality' => 'good', 'state' => 'running', 'from_osm' => null, 'hidden_at' => null]);
        $this->pyramid->invalidate($spring->longitude, $spring->latitude);
        $stats = $this->pyramid->refresh();
        self::assertSame(1, $stats['changed']);
        self::assertSame(2, $this->snapshotPoint($stats['revision'], $spring->id)['status']);
        $this->assertContainsColor($this->pyramid->tile($stats['revision'], 0, 0, 0), RasterRenderer::COLORS[2]);
        self::assertSame([], $this->paintedColors($this->pyramid->tile($this->revision, 0, 0, 0, 63, true)));

        $beforeMove = $this->snapshotPoint($stats['revision'], $spring->id);
        $this->pyramid->invalidate($spring->longitude, $spring->latitude);
        $spring->forceFill(['longitude' => 140.765432, 'latitude' => -48.345678])->save();
        $this->pyramid->invalidate($spring->longitude, $spring->latitude);
        $stats = $this->pyramid->refresh();
        $afterMove = $this->snapshotPoint($stats['revision'], $spring->id);
        self::assertSame(1, $stats['changed']);
        self::assertNotSame($beforeMove['x'], $afterMove['x']);
        self::assertNotSame($beforeMove['y'], $afterMove['y']);
        self::assertEqualsWithDelta(140.765432, $afterMove['longitude'], 1e-7);

        $report->forceFill(['state' => 'notfound', 'quality' => null])->save();
        $this->pyramid->invalidate($spring->longitude, $spring->latitude);
        $stats = $this->pyramid->refresh();
        self::assertSame(4, $this->snapshotPoint($stats['revision'], $spring->id)['status']);
        self::assertSame(0, (int) RasterSnapshot::open($this->snapshotPath($stats['revision']))->query('SELECT COUNT(*) FROM cells')->fetchColumn());
        self::assertSame([], $this->paintedColors($this->pyramid->tile($stats['revision'], 0, 0, 0)));

        $spring->hidden_at = now();
        $spring->save();
        $this->pyramid->invalidate($spring->longitude, $spring->latitude);
        $stats = $this->pyramid->refresh();
        self::assertSame(1, $stats['changed']);
        self::assertSame(0, $stats['source_count']);
        self::assertNull($this->snapshotPoint($stats['revision'], $spring->id));
        self::assertSame('', file_get_contents($this->rasterRoot.'/dirty.jsonl'));
        self::assertSame($oldHash, hash_file('sha256', $oldSnapshot));
    }

    public function test_noop_refresh_keeps_the_current_revision_and_coalesces_repeated_invalidations(): void
    {
        $spring = Spring::factory()->create(['longitude' => 139.123456, 'latitude' => -47.654321, 'type' => 'Spring']);
        $this->publishFixture([RasterSnapshot::pointFromSpring($spring, 10)], 10);
        $hash = hash_file('sha256', $this->snapshotPath($this->revision));
        self::assertSame(['changed' => 0], $this->pyramid->refresh());
        $this->pyramid->invalidate($spring->longitude, $spring->latitude);
        $this->pyramid->invalidate($spring->longitude, $spring->latitude);
        $this->pyramid->invalidate(NAN, 0);
        $stats = $this->pyramid->refresh();
        self::assertSame(0, $stats['changed']);
        self::assertSame($this->revision, $this->pyramid->manifest()['revision']);
        self::assertSame($hash, hash_file('sha256', $this->snapshotPath($this->revision)));
        self::assertCount(1, File::directories($this->rasterRoot.'/generations'));
        self::assertSame('', file_get_contents($this->rasterRoot.'/dirty.jsonl'));
    }

    public function test_failed_refresh_keeps_the_published_generation_and_requeues_work_for_retry(): void
    {
        $this->publishFixture([], 10);
        $spring = Spring::factory()->create(['longitude' => 139.123456, 'latitude' => -47.654321, 'type' => 'Spring']);
        $other = Spring::factory()->create(['longitude' => 140.765432, 'latitude' => -48.345678, 'type' => 'Water tap']);
        $this->pyramid->invalidate($spring->longitude, $spring->latitude);
        $arrival = fn () => $this->pyramid->invalidate($other->longitude, $other->latitude);
        app()->instance(RasterRenderer::class, new class($arrival)
        {
            public function __construct(private Closure $arrival) {}

            public function png(iterable $cells, int $zoom = 0): string
            {
                ($this->arrival)();
                throw new RuntimeException('Deliberate renderer failure.');
            }
        });

        try {
            $this->pyramid->refresh();
            self::fail('Expected the failed renderer to abort publication.');
        } catch (RuntimeException $exception) {
            self::assertSame('Deliberate renderer failure.', $exception->getMessage());
        }

        self::assertSame($this->revision, $this->pyramid->manifest()['revision']);
        self::assertCount(1, File::directories($this->rasterRoot.'/generations'));
        self::assertCount(2, $this->pendingCoordinates());
        app()->instance(RasterRenderer::class, new RasterRenderer);
        $stats = (new RasterPyramid)->refresh();
        self::assertSame(2, $stats['changed']);
        self::assertSame(2, $stats['source_count']);
        self::assertNotNull($this->snapshotPoint($stats['revision'], $spring->id));
        self::assertNotNull($this->snapshotPoint($stats['revision'], $other->id));
        self::assertSame([], $this->pendingCoordinates());
    }

    public function test_a_fresh_worker_replays_claimed_work_after_an_interrupted_worker(): void
    {
        $this->publishFixture([], 10);
        $spring = Spring::factory()->create(['longitude' => 139.123456, 'latitude' => -47.654321, 'type' => 'Spring']);
        $this->pyramid->invalidate($spring->longitude, $spring->latitude);
        rename($this->rasterRoot.'/dirty.jsonl', $this->rasterRoot.'/processing.jsonl');
        $stats = (new RasterPyramid)->refresh();
        self::assertSame(1, $stats['changed']);
        self::assertSame(1, $stats['source_count']);
        self::assertNotNull($this->snapshotPoint($stats['revision'], $spring->id));
        self::assertSame([], $this->pendingCoordinates());

        // A crash after publication but before acknowledging must replay safely.
        file_put_contents($this->rasterRoot.'/processing.jsonl', json_encode([$spring->longitude, $spring->latitude])."\n");
        $replayed = (new RasterPyramid)->refresh();
        self::assertSame(0, $replayed['changed']);
        self::assertSame($stats['revision'], $this->pyramid->manifest()['revision']);
        self::assertSame([], $this->pendingCoordinates());
    }

    public function test_coordinates_arriving_after_the_snapshot_copy_wait_for_the_next_refresh(): void
    {
        $this->publishFixture([], 10);
        $spring = Spring::factory()->create(['longitude' => 139.123456, 'latitude' => -47.654321, 'type' => 'Spring']);
        $this->pyramid->invalidate($spring->longitude, $spring->latitude);
        $other = null;
        $arrival = function () use (&$other): void {
            $other = Spring::factory()->create(['longitude' => 140.765432, 'latitude' => -48.345678, 'type' => 'Water tap']);
            $this->pyramid->invalidate($other->longitude, $other->latitude);
        };
        app()->instance(RasterRenderer::class, new class($arrival)
        {
            private bool $arrived = false;

            public function __construct(private Closure $arrival) {}

            public function png(iterable $cells, int $zoom = 0): string
            {
                if (! $this->arrived) {
                    $this->arrived = true;
                    ($this->arrival)();
                }

                return (new RasterRenderer)->png($cells, $zoom);
            }
        });

        $first = $this->pyramid->refresh();
        self::assertSame(1, $first['changed']);
        self::assertSame(1, $first['source_count']);
        self::assertNotNull($other);
        self::assertNull($this->snapshotPoint($first['revision'], $other->id));
        self::assertCount(1, $this->pendingCoordinates());
        $hash = hash_file('sha256', $this->snapshotPath($first['revision']));
        app()->instance(RasterRenderer::class, new RasterRenderer);
        $next = (new RasterPyramid)->refresh();
        self::assertSame(1, $next['changed']);
        self::assertSame(2, $next['source_count']);
        self::assertNotNull($this->snapshotPoint($next['revision'], $spring->id));
        self::assertNotNull($this->snapshotPoint($next['revision'], $other->id));
        self::assertSame([], $this->pendingCoordinates());
        self::assertSame($hash, hash_file('sha256', $this->snapshotPath($first['revision'])));
    }

    public function test_retention_keeps_the_active_and_previous_generation_while_removing_expired_older_sets(): void
    {
        config(['source_raster.retention_hours' => 1]);
        $this->publishFixture([], 10);
        $previous = dirname($this->snapshotPath($this->revision));

        foreach ([str_repeat('b', 24), str_repeat('c', 24)] as $index => $revision) {
            $directory = dirname($this->snapshotPath($revision));
            File::ensureDirectoryExists($directory);
            copy($previous.'/index.sqlite', $directory.'/index.sqlite');
            copy($previous.'/metadata.json', $directory.'/metadata.json');
            touch($directory, time() - (3 + $index) * 3600);
        }

        touch($previous, time() - 2 * 3600);
        clearstatcache();
        $spring = Spring::factory()->create(['longitude' => 139.123456, 'latitude' => -47.654321, 'type' => 'Spring']);
        $this->pyramid->invalidate($spring->longitude, $spring->latitude);
        $stats = $this->pyramid->refresh();
        self::assertFileExists($this->snapshotPath($stats['revision']));
        self::assertFileExists($this->snapshotPath($this->revision));
        self::assertFileDoesNotExist($this->snapshotPath(str_repeat('b', 24)));
        self::assertFileDoesNotExist($this->snapshotPath(str_repeat('c', 24)));
        self::assertCount(2, File::directories($this->rasterRoot.'/generations'));
    }

    public function test_unbuilt_and_invalid_requests_do_not_publish_or_render_a_generation(): void
    {
        self::assertNull($this->pyramid->manifest());
        self::assertSame(0, $this->pyramid->refresh()['changed']);
        $this->publishFixture([], 0);
        $this->expectException(InvalidArgumentException::class);
        $this->pyramid->tile($this->revision, 1, 0, 0);
    }

    private function publishFixture(array $points, int $maxZoom): void
    {
        $directory = dirname($this->snapshotPath($this->revision));
        File::ensureDirectoryExists($directory);
        $stats = RasterSnapshot::populateFromPoints($directory.'/index.sqlite', $points, $maxZoom);
        file_put_contents($directory.'/metadata.json', json_encode($stats, JSON_THROW_ON_ERROR));
        file_put_contents($this->rasterRoot.'/manifest.json', json_encode(['revision' => $this->revision, 'max_zoom' => $maxZoom], JSON_THROW_ON_ERROR));
    }

    private function point(int $id, int $x, int $y, int $maxZoom, int $type = 1, int $status = 0): array
    {
        $world = 512 * (1 << $maxZoom);

        return ['id' => $id, 'x' => $x, 'y' => $y, 'type' => $type, 'status' => $status,
            'longitude' => ($x + 0.5) / $world * 360 - 180,
            'latitude' => rad2deg(atan(sinh(M_PI * (1 - 2 * ($y + 0.5) / $world)))),
        ];
    }

    private function snapshotPath(string $revision): string
    {
        return $this->rasterRoot.'/generations/'.$revision.'/index.sqlite';
    }

    private function pendingCoordinates(): array
    {
        $coordinates = [];

        foreach (['dirty.jsonl', 'processing.jsonl'] as $name) {
            $path = $this->rasterRoot.'/'.$name;

            foreach (is_file($path) ? file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [] as $line) {
                $coordinates[$line] = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
            }
        }

        return array_values($coordinates);
    }

    private function snapshotPoint(string $revision, int $id): ?array
    {
        $query = RasterSnapshot::open($this->snapshotPath($revision))->prepare('SELECT * FROM points WHERE id = ?');
        $query->execute([$id]);

        return $query->fetch() ?: null;
    }

    private function rectangle(float $west, float $south, float $east, float $north): array
    {
        return ['type' => 'Polygon', 'coordinates' => [[[$west, $south], [$east, $south], [$east, $north], [$west, $north], [$west, $south]]]];
    }

    private function pixel(string $path, int $x, int $y): array
    {
        $image = imagecreatefrompng($path);
        $color = imagecolorsforindex($image, imagecolorat($image, $x, $y));
        imagedestroy($image);

        return $color;
    }

    private function assertPixelColor(string $path, int $x, int $y, array $color): void
    {
        $pixel = $this->pixel($path, $x, $y);
        self::assertSame($color, [$pixel['red'], $pixel['green'], $pixel['blue']]);
        self::assertLessThan(127, $pixel['alpha']);
    }

    private function assertTransparent(string $path, int $x, int $y): void
    {
        self::assertSame(127, $this->pixel($path, $x, $y)['alpha']);
    }

    // The selected profile draws a lone z0 source at 0.5% opacity, below GD's 7-bit alpha.
    private function visibleWorldBlue(): void
    {
        config(['source_raster.style.zooms.0.blue' => [1, 1]]);
    }

    private function paintedColors(string $path): array
    {
        $image = imagecreatefrompng($path);
        $colors = [];

        for ($y = 0; $y < imagesy($image); $y++) {
            for ($x = 0; $x < imagesx($image); $x++) {
                $pixel = imagecolorsforindex($image, imagecolorat($image, $x, $y));

                if ($pixel['alpha'] < 127) {
                    $colors[$pixel['red'].','.$pixel['green'].','.$pixel['blue']] = true;
                }
            }
        }

        imagedestroy($image);

        return array_keys($colors);
    }

    private function assertContainsColor(string $path, array $color): void
    {
        self::assertContains(implode(',', $color), $this->paintedColors($path));
    }

    private function assertLacksColor(string $path, array $color): void
    {
        self::assertNotContains(implode(',', $color), $this->paintedColors($path));
    }
}
