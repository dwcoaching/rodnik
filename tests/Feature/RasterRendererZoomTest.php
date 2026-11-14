<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\SourceRaster\RasterRenderer;
use GdImage;
use Tests\TestCase;

final class RasterRendererZoomTest extends TestCase
{
    public function test_output_is_a_valid_transparent_retina_png(): void
    {
        $empty = $this->render([], 5);
        self::assertSame([512, 512], [imagesx($empty), imagesy($empty)]);
        self::assertSame(127, imagecolorsforindex($empty, imagecolorat($empty, 10, 10))['alpha']);
        imagedestroy($empty);

        self::assertSame(array_fill(0, 512 * 512 * 4, 0), $this->rgba([], 5));
    }

    public function test_marks_use_the_configured_size_and_opacity_for_each_zoom(): void
    {
        $style = $this->style(['blue' => [4, 0.5], 'yellow' => [6, 0.25]], 10);
        $pixels = $this->rgba([$this->cell(100, 100), $this->cell(300, 300, 1)], 10, $style);

        self::assertSame([35, 143, 225, 128], $this->at($pixels, 100, 100));
        self::assertSame([231, 164, 0, 64], $this->at($pixels, 300, 300));
        // 4 CSS px = 8 physical px across; 6 CSS px = 12.
        self::assertSame(0, $this->at($pixels, 105, 100)[3]);
        self::assertGreaterThan(0, $this->at($pixels, 103, 100)[3]);
        self::assertGreaterThan(0, $this->at($pixels, 305, 300)[3]);
        self::assertSame(0, $this->at($pixels, 307, 300)[3]);

        $renderer = new RasterRenderer;
        $cells = [$this->cell(256, 256), $this->cell(280, 256, 2)];
        self::assertNotSame($renderer->png($cells, 0), $renderer->png($cells, 5));
        self::assertNotSame($renderer->png($cells, 5), $renderer->png($cells, 10));
    }

    public function test_coincident_blue_sources_combine_their_transparency(): void
    {
        $style = $this->style(['blue' => [4, 0.5]], 10);

        self::assertSame(128, $this->at($this->rgba([$this->cell(50, 50)], 10, $style), 50, 50)[3]);
        self::assertSame(191, $this->at($this->rgba([$this->cell(50, 50, 0, 2)], 10, $style), 50, 50)[3]);
        // Types are summed per pixel before combining.
        self::assertSame(191, $this->at($this->rgba([$this->cell(50, 50), $this->cell(50, 50)], 10, $style), 50, 50)[3]);
    }

    public function test_faint_world_blue_keeps_eight_bit_alpha(): void
    {
        $style = $this->style(['blue' => [0.4, 0.005]], 0);

        // 0.4 CSS px = 0.8 physical px, so coverage 0.64 at an opacity of 0.5%.
        self::assertSame(1, $this->at($this->rgba([$this->cell(10, 10)], 0, $style), 10, 10)[3]);
        self::assertSame((int) round(255 * 0.64 * (1 - 0.995 ** 100)), $this->at($this->rgba([$this->cell(10, 10, 0, 100)], 0, $style), 10, 10)[3]);
    }

    public function test_neighbouring_blue_marks_accumulate_by_source_over(): void
    {
        $style = $this->style(['blue' => [4, 0.5]], 10);
        $single = $this->rgba([$this->cell(100, 100)], 10, $style);
        $pair = $this->rgba([$this->cell(100, 100), $this->cell(101, 100)], 10, $style);

        self::assertSame(128, $this->at($single, 100, 100)[3]);
        self::assertSame(191, $this->at($pair, 100, 100)[3]);
    }

    public function test_condition_colors_survive_coincident_blue_and_other_conditions(): void
    {
        foreach ([0, 8, 10] as $zoom) {
            $coincident = $this->render([
                $this->cell(256, 256, 0, 100000),
                $this->cell(256, 256, 1),
                $this->cell(256, 256, 2),
                $this->cell(256, 256, 3),
            ], $zoom);

            foreach ([1, 2, 3] as $status) {
                self::assertGreaterThan(0, $this->colorPixels($coincident, RasterRenderer::COLORS[$status]), "Condition {$status} disappeared at z{$zoom}.");
            }

            imagedestroy($coincident);
        }
    }

    public function test_color_layers_follow_the_configured_draw_order(): void
    {
        $cells = [$this->cell(100, 100, 2), $this->cell(100, 100, 3)];
        $style = $this->style(['green' => [6, 1], 'red' => [6, 1]], 10);

        $redOnTop = $this->rgba($cells, 10, [...$style, 'order' => ['blue', 'yellow', 'green', 'red']]);
        $greenOnTop = $this->rgba($cells, 10, [...$style, 'order' => ['blue', 'yellow', 'red', 'green']]);

        // Multicolor marks are offset one pixel apart: green left, red right.
        self::assertSame([224, 48, 43, 255], $this->at($redOnTop, 100, 101));
        self::assertSame([0, 145, 69, 255], $this->at($greenOnTop, 100, 101));
    }

    public function test_tile_gutter_covers_every_configured_mark(): void
    {
        self::assertLessThanOrEqual(RasterRenderer::GUTTER, (new RasterRenderer)->reach());
    }

    public function test_horizontal_tile_seam_matches_the_same_sources_rendered_away_from_an_edge(): void
    {
        $cells = [
            $this->cell(510, 100), $this->cell(511, 100),
            $this->cell(512, 100), $this->cell(513, 100),
            $this->cell(511, 101), $this->cell(512, 101),
            $this->cell(512, 108, 2), $this->cell(514, 108, 3),
            $this->cell(507, 112), $this->cell(516, 112),
            $this->cell(518, 120, 1), $this->cell(505, 124, 3),
        ];

        foreach ([0, 8, 10] as $zoom) {
            $reference = $this->rgba($this->translate($cells, -256, 0), $zoom);
            $left = $this->rgba($cells, $zoom);
            $right = $this->rgba($this->translate($cells, -512, 0), $zoom);

            for ($y = 90; $y <= 132; $y++) {
                for ($x = 500; $x <= 524; $x++) {
                    $tile = $x < 512 ? $left : $right;
                    self::assertSame($this->at($reference, $x - 256, $y), $this->at($tile, $x % 512, $y), "Tile seam differs at z{$zoom}, {$x},{$y}.");
                }
            }
        }
    }

    public function test_four_tile_corner_preserves_neighborhoods_and_multicolor_marks(): void
    {
        $cells = [
            $this->cell(510, 511), $this->cell(511, 511),
            $this->cell(512, 511), $this->cell(511, 512),
            $this->cell(512, 512), $this->cell(513, 512),
            $this->cell(512, 512, 1), $this->cell(512, 512, 2),
            $this->cell(512, 512, 3), $this->cell(507, 507),
            $this->cell(516, 516),
        ];

        foreach ([0, 8, 10] as $zoom) {
            $reference = $this->rgba($this->translate($cells, -256, -256), $zoom);
            $tiles = [];

            foreach ([0, 1] as $tileY) {
                foreach ([0, 1] as $tileX) {
                    $tiles[$tileY][$tileX] = $this->rgba($this->translate($cells, -512 * $tileX, -512 * $tileY), $zoom);
                }
            }

            for ($y = 500; $y <= 524; $y++) {
                for ($x = 500; $x <= 524; $x++) {
                    $tile = $tiles[intdiv($y, 512)][intdiv($x, 512)];
                    self::assertSame($this->at($reference, $x - 256, $y - 256), $this->at($tile, $x % 512, $y % 512), "Tile corner differs at z{$zoom}, {$x},{$y}.");
                }
            }
        }
    }

    private function cell(int $x, int $y, int $status = 0, int $count = 1): array
    {
        return ['px' => $x, 'py' => $y, 'status' => $status, 'n' => $count];
    }

    /** A style where the given colors apply at $zoom and every other color is hidden. */
    private function style(array $colors, int $zoom): array
    {
        $row = array_fill_keys(RasterRenderer::COLOR_NAMES, [0, 0]);

        return ['order' => RasterRenderer::COLOR_NAMES, 'zooms' => [$zoom => [...$row, ...$colors]]];
    }

    private function translate(array $cells, int $x, int $y): array
    {
        return array_map(fn (array $cell): array => [...$cell, 'px' => $cell['px'] + $x, 'py' => $cell['py'] + $y], $cells);
    }

    private function render(array $cells, int $zoom): GdImage
    {
        $image = imagecreatefromstring((new RasterRenderer)->png($cells, $zoom));
        self::assertInstanceOf(GdImage::class, $image);

        return $image;
    }

    /** Exact 8-bit RGBA bytes; GD would reduce alpha to 7 bits. */
    private function rgba(array $cells, int $zoom, ?array $style = null): array
    {
        $png = (new RasterRenderer($style))->png($cells, $zoom);
        $offset = 8;
        $data = '';
        while ($offset < strlen($png)) {
            $length = unpack('N', substr($png, $offset, 4))[1];
            if (substr($png, $offset + 4, 4) === 'IDAT') {
                $data .= substr($png, $offset + 8, $length);
            }
            $offset += 12 + $length;
        }
        $raw = gzuncompress($data);
        $pixels = [];
        for ($y = 0; $y < 512; $y++) {
            self::assertSame("\0", $raw[$y * 2049]);
            array_push($pixels, ...array_values(unpack('C*', substr($raw, $y * 2049 + 1, 2048))));
        }

        return $pixels;
    }

    private function at(array $pixels, int $x, int $y): array
    {
        $offset = ($y * 512 + $x) * 4;
        $pixel = array_slice($pixels, $offset, 4);

        // Color is irrelevant on a fully transparent pixel.
        return $pixel[3] === 0 ? [0, 0, 0, 0] : $pixel;
    }

    private function colorPixels(GdImage $image, array $color): int
    {
        $matches = 0;

        for ($y = 248; $y <= 264; $y++) {
            for ($x = 248; $x <= 264; $x++) {
                $pixel = imagecolorsforindex($image, imagecolorat($image, $x, $y));
                $matches += (int) ($pixel['alpha'] < 127 && [$pixel['red'], $pixel['green'], $pixel['blue']] === $color);
            }
        }

        return $matches;
    }
}
