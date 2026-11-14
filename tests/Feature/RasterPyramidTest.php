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
use PDO;
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
        $reports = $this->stored($this->pyramid->png(3, 2, 2, 63, true));
        $this->assertTransparent($reports, 176, 376);
        $this->assertPixelColor($reports, 216, 376, RasterRenderer::COLORS[1]);
        $this->assertPixelColor($reports, 256, 376, RasterRenderer::COLORS[2]);
        $types = $this->stored($this->pyramid->png(3, 2, 2, 1 | 4));
        $this->assertPixelColor($types, 176, 376, RasterRenderer::COLORS[0]);
        $this->assertPixelColor($types, 256, 376, RasterRenderer::COLORS[2]);
        $this->assertTransparent($types, 216, 376);
        $this->assertTransparent($types, 296, 376);
        $empty = $this->stored($this->pyramid->png(3, 2, 2, 0));
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
        $stats = $this->pyramid->restyle();
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

    public function test_an_edit_updates_counts_in_place_and_redraws_only_the_tiles_that_show_it(): void
    {
        $this->visibleWorldBlue();
        $this->publishFixture([$this->point(1, 300000, 200000, 10)], 10);
        $this->pyramid->restyle();
        $revision = $this->pyramid->manifest()['revision'];
        $untouched = $this->pyramid->tile($revision, 10, 585, 390);
        $untouchedTime = filemtime($untouched);
        $drawn = count(File::allFiles($this->rasterRoot.'/tiles/'.$revision));
        touch($untouched, $untouchedTime - 100);
        clearstatcache();

        $spring = Spring::factory()->create(['longitude' => 139.123456, 'latitude' => -47.654321, 'type' => 'Spring']);
        $this->pyramid->invalidate($spring->longitude, $spring->latitude);
        $this->pyramid->invalidate($spring->longitude, $spring->latitude);
        $stats = $this->pyramid->refresh();

        self::assertSame(1, $stats['changed']);
        self::assertSame(11, $stats['tiles']); // One per zoom 0-10, away from tile edges.
        self::assertSame($revision, $this->pyramid->manifest()['revision']);
        self::assertSame($untouchedTime - 100, filemtime($untouched));
        self::assertCount($drawn + 10, File::allFiles($this->rasterRoot.'/tiles/'.$revision)); // z0 already existed.
        self::assertSame(['1:0' => 1], $this->cellsAt($spring));
        $this->assertContainsColor($this->pyramid->tile($revision, 0, 0, 0), RasterRenderer::COLORS[0]);

        $report = Report::factory()->for($spring)->create(['quality' => 'good', 'state' => 'running', 'from_osm' => null, 'hidden_at' => null]);
        $this->pyramid->invalidate($spring->longitude, $spring->latitude);
        self::assertSame(1, $this->pyramid->refresh()['changed']);
        self::assertSame(['1:2' => 1], $this->cellsAt($spring));
        $this->assertContainsColor($this->pyramid->tile($revision, 0, 0, 0), RasterRenderer::COLORS[2]);

        $old = clone $spring;
        $this->pyramid->invalidate($spring->longitude, $spring->latitude);
        $spring->forceFill(['longitude' => 140.765432, 'latitude' => -48.345678])->save();
        $this->pyramid->invalidate($spring->longitude, $spring->latitude);
        self::assertSame(2, $this->pyramid->refresh()['changed']);
        self::assertSame([], $this->cellsAt($old));
        self::assertSame(['1:2' => 1], $this->cellsAt($spring));

        $report->forceFill(['state' => 'notfound', 'quality' => null])->save();
        $this->pyramid->invalidate($spring->longitude, $spring->latitude);
        $this->pyramid->refresh();
        self::assertSame([], $this->cellsAt($spring)); // Not-found sources are not drawn.

        $report->delete();
        $this->pyramid->invalidate($spring->longitude, $spring->latitude);
        $this->pyramid->refresh();
        self::assertSame(['1:0' => 1], $this->cellsAt($spring));

        $spring->hidden_at = now();
        $spring->save();
        $this->pyramid->invalidate($spring->longitude, $spring->latitude);
        self::assertSame(1, $this->pyramid->refresh()['changed']);
        self::assertSame([], $this->cellsAt($spring));
        self::assertSame(11, (int) $this->database()->query('SELECT COUNT(*) FROM cells')->fetchColumn()); // Only the fixture source remains.
        self::assertSame([], $this->pendingCoordinates());
        self::assertSame($revision, $this->pyramid->manifest()['revision']);
    }

    public function test_noop_refresh_changes_nothing_and_coalesces_repeated_invalidations(): void
    {
        $spring = Spring::factory()->create(['longitude' => 139.123456, 'latitude' => -47.654321, 'type' => 'Spring']);
        $this->publishFixture([RasterSnapshot::pointFromSpring($spring, 10)], 10);
        $hash = hash_file('sha256', $this->rasterRoot.'/index.sqlite');
        $manifest = file_get_contents($this->rasterRoot.'/manifest.json');
        self::assertSame(['changed' => 0], $this->pyramid->refresh());
        $this->pyramid->invalidate($spring->longitude, $spring->latitude);
        $this->pyramid->invalidate($spring->longitude, $spring->latitude);
        $this->pyramid->invalidate(NAN, 0);
        $stats = $this->pyramid->refresh();
        self::assertSame(0, $stats['changed']);
        self::assertSame(0, $stats['tiles']);
        self::assertSame($hash, hash_file('sha256', $this->rasterRoot.'/index.sqlite'));
        self::assertSame($manifest, file_get_contents($this->rasterRoot.'/manifest.json'));
        self::assertDirectoryDoesNotExist($this->rasterRoot.'/tiles');
        self::assertSame([], $this->pendingCoordinates());
    }

    public function test_a_failed_redraw_is_repeated_even_though_the_counts_were_already_saved(): void
    {
        $this->visibleWorldBlue();
        $this->publishFixture([], 10);
        $spring = Spring::factory()->create(['longitude' => 139.123456, 'latitude' => -47.654321, 'type' => 'Spring']);
        $other = Spring::factory()->create(['longitude' => 140.765432, 'latitude' => -48.345678, 'type' => 'Water tap']);
        $this->pyramid->invalidate($spring->longitude, $spring->latitude);
        $arrival = fn () => $this->pyramid->invalidate($other->longitude, $other->latitude);
        app()->instance(RasterRenderer::class, new class($arrival)
        {
            public function __construct(private Closure $arrival) {}

            public function png(iterable $cells, int $zoom = 0, ?array $style = null): string
            {
                ($this->arrival)();
                throw new RuntimeException('Deliberate renderer failure.');
            }
        });

        try {
            $this->pyramid->refresh();
            self::fail('Expected the failed renderer to abort the refresh.');
        } catch (RuntimeException $exception) {
            self::assertSame('Deliberate renderer failure.', $exception->getMessage());
        }

        self::assertSame(['1:0' => 1], $this->cellsAt($spring));
        self::assertDirectoryDoesNotExist($this->rasterRoot.'/tiles');
        self::assertCount(2, $this->pendingCoordinates());
        app()->instance(RasterRenderer::class, new RasterRenderer);
        $stats = (new RasterPyramid)->refresh();
        self::assertSame(1, $stats['changed']); // Only the later arrival still differs.
        self::assertGreaterThan(11, $stats['tiles']); // Both sources are drawn; they share far-zoom tiles.
        self::assertSame(['4:0' => 1], $this->cellsAt($other));
        $this->assertContainsColor($this->pyramid->tile($this->revision, 0, 0, 0), RasterRenderer::COLORS[0]);
        self::assertSame([], $this->pendingCoordinates());
    }

    public function test_coordinates_arriving_during_a_refresh_wait_for_the_next_one(): void
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

            public function png(iterable $cells, int $zoom = 0, ?array $style = null): string
            {
                if (! $this->arrived) {
                    $this->arrived = true;
                    ($this->arrival)();
                }

                return (new RasterRenderer)->png($cells, $zoom, $style);
            }
        });

        self::assertSame(1, $this->pyramid->refresh()['changed']);
        self::assertSame([], $this->cellsAt($other));
        self::assertCount(1, $this->pendingCoordinates());
        app()->instance(RasterRenderer::class, new RasterRenderer);
        self::assertSame(1, (new RasterPyramid)->refresh()['changed']);
        self::assertSame(['4:0' => 1], $this->cellsAt($other));
        self::assertSame([], $this->pendingCoordinates());
    }

    public function test_a_saved_style_is_applied_by_the_next_refresh_and_old_tile_sets_are_removed(): void
    {
        $this->publishFixture([$this->point(1, 256, 256, 0, 1, 2)], 0);
        $this->pyramid->restyle();
        $first = $this->pyramid->manifest()['revision'];
        $style = $this->pyramid->style();
        self::assertSame(config('source_raster.style.zooms.0.green'), $style['zooms'][0]['green']);
        self::assertFalse($this->pyramid->status()['pending']);

        $style['zooms'][0]['green'] = [8, 1];
        $this->pyramid->queueStyle($style);
        self::assertTrue($this->pyramid->status()['pending']);
        self::assertSame($first, $this->pyramid->manifest()['revision']);
        $this->assertTransparent($this->pyramid->tile($first, 0, 0, 0), 262, 256);

        $stats = $this->pyramid->refresh();
        $second = $this->pyramid->manifest()['revision'];
        self::assertTrue($stats['restyled']);
        self::assertNotSame($first, $second);
        self::assertEquals([8, 1], $this->pyramid->style()['zooms'][0]['green']);
        self::assertFalse($this->pyramid->status()['pending']);
        $this->assertPixelColor($this->pyramid->tile($second, 0, 0, 0), 262, 256, RasterRenderer::COLORS[2]);
        self::assertFileExists($this->rasterRoot.'/tiles/'.$first.'/0/0/0.png'); // Open maps keep working.

        $this->pyramid->restyle();
        self::assertDirectoryDoesNotExist($this->rasterRoot.'/tiles/'.$first);
        self::assertDirectoryExists($this->rasterRoot.'/tiles/'.$second);
    }

    public function test_invalid_styles_are_refused(): void
    {
        $this->publishFixture([], 0);
        $style = config('source_raster.style');

        foreach ([
            ['order' => ['blue', 'blue', 'green', 'red']] + $style,
            array_replace_recursive($style, ['zooms' => [3 => ['red' => [RasterRenderer::MAX_DIAMETER + 1, 0.5]]]]),
            array_replace_recursive($style, ['zooms' => [3 => ['red' => [2, 1.5]]]]),
            ['zooms' => array_slice($style['zooms'], 0, 10, true)] + $style,
        ] as $invalid) {
            try {
                $this->pyramid->queueStyle($invalid);
                self::fail('Expected the style to be refused.');
            } catch (InvalidArgumentException) {
                self::assertFalse($this->pyramid->status()['pending']);
            }
        }
    }

    public function test_previews_and_filtered_tiles_are_drawn_without_being_stored(): void
    {
        $this->publishFixture([$this->point(1, 256, 256, 0, 1, 2)], 0);
        $style = config('source_raster.style');
        $style['zooms'][0]['green'] = [8, 1];
        $published = $this->pyramid->png(0, 0, 0);
        self::assertNotSame($published, $this->pyramid->png(0, 0, 0, 63, false, $style));
        self::assertNotSame($published, $this->pyramid->png(0, 0, 0, 2));
        self::assertSame($published, $this->pyramid->png(0, 0, 0, 1, true));
        self::assertDirectoryDoesNotExist($this->rasterRoot.'/tiles');
    }

    public function test_the_raster_overview_is_off_until_switched_on(): void
    {
        $this->publishFixture([], 0);
        self::assertFalse($this->pyramid->enabled());
        self::assertFalse($this->pyramid->status()['enabled']);
        $this->pyramid->setEnabled(true);
        self::assertTrue((new RasterPyramid)->status()['enabled']);
        $this->pyramid->restyle();
        self::assertTrue($this->pyramid->enabled(), 'a redraw keeps the switch');
        $this->pyramid->setEnabled(false);
        $this->pyramid->setEnabled(false);
        self::assertFalse($this->pyramid->enabled());
    }

    public function test_refresh_reports_a_running_build_instead_of_failing(): void
    {
        $this->publishFixture([], 0);
        $lock = fopen($this->rasterRoot.'/build.lock', 'c');
        flock($lock, LOCK_EX);
        self::assertTrue($this->pyramid->status()['busy']);
        self::assertSame(0, $this->pyramid->refresh()['changed']);
        flock($lock, LOCK_UN);
        fclose($lock);
        self::assertFalse($this->pyramid->status()['busy']);
    }

    public function test_unbuilt_unknown_and_outdated_requests_do_not_draw_anything(): void
    {
        self::assertNull($this->pyramid->manifest());
        $this->pyramid->invalidate(20, 50);
        self::assertCount(1, $this->pendingCoordinates());
        self::assertSame(0, $this->pyramid->refresh()['changed']);
        self::assertSame([], $this->pendingCoordinates(), 'edits do not pile up before the first build');
        $this->publishFixture([], 0);

        foreach ([[$this->revision, 1, 0, 0], [str_repeat('b', 24), 0, 0, 0], ['../../etc', 0, 0, 0]] as $arguments) {
            try {
                $this->pyramid->tile(...$arguments);
                self::fail('Expected the tile to be refused.');
            } catch (InvalidArgumentException) {
                self::assertDirectoryDoesNotExist($this->rasterRoot.'/tiles');
            }
        }
    }

    private function publishFixture(array $points, int $maxZoom): void
    {
        File::ensureDirectoryExists($this->rasterRoot);
        RasterSnapshot::populateFromPoints($this->rasterRoot.'/index.sqlite', $points, $maxZoom);
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

    private function database(): PDO
    {
        return RasterSnapshot::open($this->rasterRoot.'/index.sqlite');
    }

    /** Finest-zoom counts at a source's pixel, keyed "type:status". */
    private function cellsAt(Spring $spring): array
    {
        $position = RasterSnapshot::project((float) $spring->longitude, (float) $spring->latitude, 10);
        $query = $this->database()->prepare('SELECT type,status,n FROM cells WHERE z=10 AND tile_x=? AND tile_y=? AND px=? AND py=?');
        $query->execute([intdiv($position['x'], 512), intdiv($position['y'], 512), $position['x'] % 512, $position['y'] % 512]);
        $cells = [];
        foreach ($query as $cell) {
            $cells[$cell['type'].':'.$cell['status']] = $cell['n'];
        }

        return $cells;
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

    /** Write drawn bytes to a file so the image helpers can read them. */
    private function stored(string $png): string
    {
        $path = $this->rasterRoot.'/'.bin2hex(random_bytes(6)).'.png';
        file_put_contents($path, $png);

        return $path;
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
