<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\SourceRaster\RasterPyramid;
use App\Services\SourceRaster\RasterSnapshot;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class RasterPolygonTest extends TestCase
{
    public function test_shell_and_hole_boundaries_are_included_but_nearby_outside_points_are_excluded(): void
    {
        $pyramid = new RasterPyramid;
        $polygon = $this->prepare($pyramid, [
            'type' => 'Polygon',
            'coordinates' => [
                [[0, 0], [10, 0], [10, 10], [0, 10], [0, 0]],
                [[3, 3], [7, 3], [7, 7], [3, 7], [3, 3]],
            ],
        ]);

        foreach ([
            [2, 2, true], [12, 2, false], [0, 5, true], [10, 10, true],
            [5, 5, false], [3, 5, true], [3, 3, true],
            [3.000000000001, 5, false], [-0.000000000001, 5, false],
            [3.000000000001, 3.000000000001, false],
        ] as [$longitude, $latitude, $expected]) {
            self::assertSame($expected, $this->inside($pyramid, $polygon, $longitude, $latitude), json_encode([$longitude, $latitude]));
        }
    }

    public function test_route_filtering_uses_original_coordinates_even_when_inside_and_outside_sources_share_a_pixel(): void
    {
        $inside = RasterSnapshot::project(0.0001, 0.0001, 10);
        $outside = RasterSnapshot::project(0.0002, 0.0001, 10);
        self::assertSame($inside, $outside);
        $cells = $this->cells([
            [0.0001, 0.0001], [0.0002, 0.0001],
        ], [
            'type' => 'Polygon',
            'coordinates' => [[[0, 0], [0.00015, 0], [0.00015, 0.001], [0, 0.001], [0, 0]]],
        ]);

        self::assertCount(1, $cells);
        self::assertSame(1, $cells[0]['n']);
    }

    public function test_multipolygons_do_not_double_count_a_source_and_retain_report_and_type_filters(): void
    {
        $geometry = [
            'type' => 'MultiPolygon',
            'coordinates' => [
                [[[0, 0], [10, 0], [10, 10], [0, 10], [0, 0]]],
                [[[5, 5], [15, 5], [15, 15], [5, 15], [5, 5]]],
                [[[30, 30], [31, 30], [31, 31], [30, 31], [30, 30]]],
            ],
        ];
        $points = [[6, 6, 2, 1], [12, 12, 0, 1], [30.5, 30.5, 3, 2], [20, 20, 2, 1], [7, 7, 4, 1]];

        self::assertCount(3, $this->cells($points, $geometry));
        $reported = $this->cells($points, $geometry, reports: true);
        self::assertCount(2, $reported);
        self::assertSame([2, 3], array_column($reported, 'status'));
        self::assertCount(1, $this->cells($points, $geometry, types: 2));
    }

    public function test_route_selection_retains_density_counts_when_multiple_sources_share_a_display_pixel(): void
    {
        $cells = $this->cells([[0.0001, 0.0001], [0.0002, 0.0001], [0.0001, 0.0001, 2]], [
            'type' => 'Polygon',
            'coordinates' => [[[-1, -1], [1, -1], [1, 1], [-1, 1], [-1, -1]]],
        ]);

        self::assertCount(2, $cells);
        $counts = array_column($cells, 'n', 'status');
        self::assertSame([0 => 2, 2 => 1], $counts);
    }

    public function test_antimeridian_copies_use_floor_division_and_preserve_the_east_boundary(): void
    {
        $cells = $this->cells([[179.999, 5], [-180, 5]], [
            'type' => 'Polygon',
            'coordinates' => [[[179, 0], [180, 0], [180, 10], [179, 10], [179, 0]]],
        ]);
        $pixels = array_column($cells, 'px');
        sort($pixels);

        self::assertSame([-1, 0, 511, 512], $pixels);
    }

    public function test_large_narrow_route_geometry_handles_near_limit_vertex_counts(): void
    {
        $upper = $lower = $centers = [];
        for ($index = 0; $index < 24999; $index++) {
            $fraction = $index / 24998;
            $longitude = -84 + 17 * $fraction;
            $latitude = 35 + 10 * $fraction + 0.1 * sin($fraction * 300);
            $centers[] = [$longitude, $latitude];
            $upper[] = [$longitude, $latitude + 0.003];
            $lower[] = [$longitude, $latitude - 0.003];
        }
        $ring = [...$upper, ...array_reverse($lower), $upper[0]];
        self::assertCount(49999, $ring);
        $pyramid = new RasterPyramid;
        $polygon = $this->prepare($pyramid, ['type' => 'Polygon', 'coordinates' => [$ring]]);

        for ($index = 1; $index < count($centers) - 1; $index += 200) {
            [$longitude, $latitude] = $centers[$index];
            self::assertTrue($this->inside($pyramid, $polygon, $longitude, $latitude));
            self::assertFalse($this->inside($pyramid, $polygon, $longitude, $latitude + 0.02));
        }
    }

    private function prepare(RasterPyramid $pyramid, array $geometry): array
    {
        return (new ReflectionMethod(RasterPyramid::class, 'preparePolygons'))->invoke($pyramid, $geometry);
    }

    private function inside(RasterPyramid $pyramid, array $polygon, float $longitude, float $latitude): bool
    {
        return (new ReflectionMethod(RasterPyramid::class, 'inside'))->invoke($pyramid, $longitude, $latitude, $polygon);
    }

    private function cells(array $points, array $polygon, int $types = 63, bool $reports = false): array
    {
        $database = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $database->exec('CREATE TABLE points (id INTEGER PRIMARY KEY, x INTEGER, y INTEGER, status INTEGER, type INTEGER, longitude REAL, latitude REAL)');
        $insert = $database->prepare('INSERT INTO points VALUES (?, ?, ?, ?, ?, ?, ?)');
        foreach ($points as $index => $point) {
            [$longitude, $latitude] = $point;
            $pixel = RasterSnapshot::project($longitude, $latitude, 10);
            $insert->execute([$index + 1, $pixel['x'], $pixel['y'], $point[2] ?? 0, $point[3] ?? 1, $longitude, $latitude]);
        }
        $generator = (new ReflectionMethod(RasterPyramid::class, 'polygonCells'))->invoke(new RasterPyramid, $database, 10, 0, 0, 0, $types, $reports, $polygon);

        return iterator_to_array($generator, false);
    }
}
