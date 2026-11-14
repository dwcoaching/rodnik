<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Report;
use App\Models\Spring;
use App\Services\SourceRaster\RasterSnapshot;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;
use PDOException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RasterSnapshotTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();
        $this->path = sys_get_temp_dir().'/rodnik-raster-'.bin2hex(random_bytes(8)).'.sqlite';
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }

        parent::tearDown();
    }

    public function test_aggregation_retains_every_source_and_each_type_and_condition_at_every_zoom(): void
    {
        $points = [
            $this->point(1, -0.0001, 0.0001, 1, 0),
            $this->point(2, -0.0001, 0.0001, 1, 0),
            $this->point(3, -0.0001, 0.0001, 1, 2),
            $this->point(4, -0.0001, 0.0001, 2, 3),
            $this->point(5, 179.9, -40, 32, 1),
            $this->point(6, -179.9, 40, 8, 4),
            $this->point(7, 40, 65, 16, 0),
        ];
        $progress = [];
        $stats = RasterSnapshot::populateFromPoints($this->path, $points, 3, function (string $stage, int $value) use (&$progress): void {
            $progress[] = [$stage, $value];
        });
        $database = RasterSnapshot::open($this->path);

        self::assertSame(7, $stats['source_count']);
        self::assertSame(3, $stats['max_zoom']);
        self::assertGreaterThan(0, $stats['bytes']);
        self::assertSame(7, (int) $database->query('SELECT COUNT(*) FROM points')->fetchColumn());
        self::assertSame((int) $database->query('SELECT COUNT(*) FROM cells')->fetchColumn(), $stats['cell_count']);
        self::assertSame([['sources', 7], ['zoom', 3], ['zoom', 2], ['zoom', 1], ['zoom', 0]], $progress);

        for ($zoom = 0; $zoom <= 3; $zoom++) {
            $rows = $database->query("SELECT * FROM cells WHERE z = {$zoom}")->fetchAll();
            self::assertSame(6, array_sum(array_column($rows, 'n')));
            self::assertSame(0, (int) $database->query("SELECT COUNT(*) FROM cells WHERE z = {$zoom} AND status = 4")->fetchColumn());
            self::assertSame(3, (int) $database->query("SELECT SUM(n) FROM cells WHERE z = {$zoom} AND type = 1")->fetchColumn());
            self::assertSame(1, (int) $database->query("SELECT SUM(n) FROM cells WHERE z = {$zoom} AND status = 2")->fetchColumn());

            // Compare every cell with direct aggregation from the original
            // points, including locations on either side of a tile boundary.
            $expected = [];

            foreach ($points as $point) {
                if ($point['status'] === 4) {
                    continue;
                }

                $x = intdiv($point['x'], 1 << (3 - $zoom));
                $y = intdiv($point['y'], 1 << (3 - $zoom));
                $key = implode(',', [intdiv($x, 512), intdiv($y, 512), $x % 512, $y % 512, $point['type'], $point['status']]);
                $expected[$key] = ($expected[$key] ?? 0) + 1;
            }

            $actual = [];

            foreach ($rows as $row) {
                $actual[implode(',', [$row['tile_x'], $row['tile_y'], $row['px'], $row['py'], $row['type'], $row['status']])] = $row['n'];
            }

            ksort($expected);
            ksort($actual);
            self::assertSame($expected, $actual);
        }
    }

    public function test_projection_handles_the_antimeridian_equator_poles_and_invalid_coordinates(): void
    {
        self::assertSame(['x' => 2048, 'y' => 2048], RasterSnapshot::project(0, 0, 3));
        self::assertSame(RasterSnapshot::project(-180, 20, 3), RasterSnapshot::project(180, 20, 3));
        self::assertSame(0, RasterSnapshot::project(0, 90, 3)['y']);
        self::assertSame(4095, RasterSnapshot::project(0, -90, 3)['y']);
        self::assertNull(RasterSnapshot::project(181, 0, 3));
        self::assertNull(RasterSnapshot::project(0, -91, 3));
        self::assertNull(RasterSnapshot::project(NAN, 0, 3));
        self::assertNull(RasterSnapshot::project(0, INF, 3));
    }

    public function test_empty_snapshot_is_valid_and_published_connections_are_read_only(): void
    {
        $stats = RasterSnapshot::populateFromPoints($this->path, [], 0);
        $database = RasterSnapshot::open($this->path);
        self::assertSame(0, $stats['source_count']);
        self::assertSame(0, $stats['cell_count']);
        self::assertSame('0', $database->query("SELECT value FROM metadata WHERE key = 'max_zoom'")->fetchColumn());

        $this->expectException(PDOException::class);
        $database->exec('DELETE FROM points');
    }

    public function test_build_refuses_to_replace_an_existing_snapshot(): void
    {
        file_put_contents($this->path, 'published');

        try {
            RasterSnapshot::populateFromPoints($this->path, [], 0);
            self::fail('Expected an existing-file exception.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('already exists', $exception->getMessage());
            self::assertSame('published', file_get_contents($this->path));
        }
    }

    public function test_failure_cleans_up_the_unpublished_file(): void
    {
        $point = $this->point(1, 0, 0);
        $point['x'] = -1;

        try {
            RasterSnapshot::populateFromPoints($this->path, [$point], 3);
            self::fail('Expected an invalid-point exception.');
        } catch (InvalidArgumentException) {
            self::assertFileDoesNotExist($this->path);
        }
    }

    public function test_spring_status_preserves_visible_report_scoring_and_not_found_semantics(): void
    {
        $cases = [
            [[], 0],
            [[[]], 1],
            [[['quality' => 'good']], 2],
            [[['quality' => 'bad']], 3],
            [[['state' => 'dry']], 3],
            [[['quality' => 'good', 'access_limited' => true]], 1],
            [[['quality' => 'good'], ['quality' => 'bad']], 1],
            [[['state' => 'notfound']], 4],
            [[['state' => 'notfound'], []], 4],
            [[['state' => 'notfound'], ['state' => 'running']], 1],
            [[['state' => 'notfound'], ['quality' => 'good']], 2],
            [[['state' => 'notfound'], ['littered' => true]], 1],
            [[['quality' => 'good'], ['quality' => 'good'], ['quality' => 'bad'], ['quality' => 'good'], ['state' => 'running']], 2],
        ];

        foreach ($cases as [$reports, $expected]) {
            $spring = $this->spring($reports);
            $point = RasterSnapshot::pointFromSpring($spring, 3);
            self::assertSame($expected, $point['status'], json_encode($reports));
            self::assertSame($expected === 4, $spring->isNotFound());
        }
    }

    public function test_hidden_redirected_and_invalid_springs_are_excluded_and_types_are_stable_bits(): void
    {
        foreach (Spring::TYPES as $index => $type) {
            $spring = $this->spring([]);
            $spring->type = $type;
            self::assertSame(1 << $index, RasterSnapshot::pointFromSpring($spring, 3)['type']);
        }

        self::assertSame(32, RasterSnapshot::typeBit(null));
        self::assertSame(32, RasterSnapshot::typeBit('Unknown'));

        foreach ([['hidden_at' => '2026-10-28'], ['redirect_to_spring_id' => 12], ['latitude' => null], ['longitude' => 181]] as $attributes) {
            $spring = $this->spring([]);
            $spring->forceFill($attributes);
            self::assertNull(RasterSnapshot::pointFromSpring($spring, 3));
        }
    }

    private function point(int $id, float $longitude, float $latitude, int $type = 1, int $status = 0): array
    {
        return ['id' => $id, ...RasterSnapshot::project($longitude, $latitude, 3), 'type' => $type, 'status' => $status, 'longitude' => $longitude, 'latitude' => $latitude];
    }

    private function spring(array $reports): Spring
    {
        $spring = new Spring;
        $spring->forceFill(['id' => 1, 'type' => 'Spring', 'longitude' => 20, 'latitude' => 50]);
        $spring->setRelation('visibleReports', new Collection(array_map(function (array $attributes): Report {
            $report = new Report;
            $report->forceFill($attributes);

            return $report;
        }, $reports)));

        return $spring;
    }
}
