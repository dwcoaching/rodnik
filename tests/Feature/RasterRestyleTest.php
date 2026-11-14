<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\SourceRaster\RasterPyramid;
use App\Services\SourceRaster\RasterRenderer;
use App\Services\SourceRaster\RasterSnapshot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;

final class RasterRestyleTest extends TestCase
{
    private string $root;

    private string $revision;

    private RasterPyramid $pyramid;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir().'/rodnik-restyle-'.bin2hex(random_bytes(8));
        config(['source_raster.root' => $this->root]);
        $this->revision = str_repeat('a', 24);
        $this->pyramid = new RasterPyramid;
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    public function test_restyle_reuses_the_counts_draws_each_zoom_and_keeps_the_previous_tiles(): void
    {
        $this->publishFixture();
        $snapshotHash = hash_file('sha256', $this->root.'/index.sqlite');
        $oldTile = $this->pyramid->tile($this->revision, 0, 0, 0);
        $tileHash = hash_file('sha256', $oldTile);
        file_put_contents($this->root.'/dirty.jsonl', "[20,50]\n");
        DB::partialMock()->shouldNotReceive('table');

        $renderer = new class
        {
            public array $zooms = [];

            public function png(iterable $cells, int $zoom = 0, ?array $style = null): string
            {
                $this->zooms[] = $zoom;

                return (new RasterRenderer)->png($cells, $zoom, $style);
            }
        };
        app()->instance(RasterRenderer::class, $renderer);
        $stats = $this->pyramid->restyle();
        $manifest = $this->pyramid->manifest();

        self::assertNotSame($this->revision, $stats['revision']);
        self::assertSame($stats['revision'], $manifest['revision']);
        self::assertSame($this->revision, $manifest['previous_revision']);
        self::assertEquals(config('source_raster.style'), $manifest['style']);
        self::assertGreaterThanOrEqual(3, $stats['tiles']);
        self::assertGreaterThan(0, $stats['png_bytes']);
        self::assertEqualsCanonicalizing([0, 1, 2], array_values(array_unique($renderer->zooms)));
        self::assertSame($snapshotHash, hash_file('sha256', $this->root.'/index.sqlite'));
        self::assertSame($tileHash, hash_file('sha256', $oldTile));
        self::assertSame("[20,50]\n", file_get_contents($this->root.'/dirty.jsonl'));
        self::assertStringContainsString($stats['revision'], $manifest['url']);
        self::assertNotSame($oldTile, $this->pyramid->tile($stats['revision'], 0, 0, 0));
    }

    public function test_no_render_command_publishes_a_new_revision_that_draws_on_demand(): void
    {
        $this->publishFixture();
        DB::partialMock()->shouldNotReceive('table');
        app()->instance(RasterRenderer::class, new class
        {
            public function png(iterable $cells, int $zoom = 0, ?array $style = null): string
            {
                throw new RuntimeException('A no-render restyle must not draw PNGs.');
            }
        });

        $this->artisan('sources:raster-restyle', ['--no-render' => true])->assertSuccessful();
        $manifest = $this->pyramid->manifest();

        self::assertNotSame($this->revision, $manifest['revision']);
        self::assertSame($this->revision, $manifest['previous_revision']);
        self::assertSame(0, $manifest['statistics']['tiles']);
        self::assertDirectoryDoesNotExist($this->root.'/tiles/'.$manifest['revision']);

        app()->instance(RasterRenderer::class, new RasterRenderer);
        self::assertFileExists($this->pyramid->tile($manifest['revision'], 0, 0, 0));
    }

    public function test_failed_restyle_keeps_the_existing_manifest_and_leaves_no_partial_tiles(): void
    {
        $this->publishFixture();
        $hash = hash_file('sha256', $this->root.'/index.sqlite');
        app()->instance(RasterRenderer::class, new class
        {
            public function png(iterable $cells, int $zoom = 0, ?array $style = null): string
            {
                throw new RuntimeException('Deliberate restyle failure.');
            }
        });

        try {
            $this->pyramid->restyle();
            self::fail('Expected rendering to abort publication.');
        } catch (RuntimeException $exception) {
            self::assertSame('Deliberate restyle failure.', $exception->getMessage());
        }

        self::assertSame($this->revision, $this->pyramid->manifest()['revision']);
        self::assertSame($hash, hash_file('sha256', $this->root.'/index.sqlite'));
        self::assertSame([], glob($this->root.'/tiles/*'));
    }

    public function test_restyle_requires_an_existing_snapshot(): void
    {
        $this->artisan('sources:raster-restyle', ['--no-render' => true])
            ->expectsOutput('Build the source overview before restyling it.')
            ->assertFailed();
        self::assertNull($this->pyramid->manifest());
    }

    private function publishFixture(): void
    {
        File::ensureDirectoryExists($this->root);
        RasterSnapshot::populateFromPoints($this->root.'/index.sqlite', [[
            ...RasterSnapshot::project(20, 50, 2),
            'type' => 1,
            'status' => 2,
        ]], 2);
        file_put_contents($this->root.'/manifest.json', json_encode(['revision' => $this->revision, 'max_zoom' => 2], JSON_THROW_ON_ERROR));
    }
}
