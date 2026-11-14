<?php

declare(strict_types=1);

namespace App\Services\SourceRaster;

use RuntimeException;

/**
 * Draws one 512px (2x retina) overview tile from numeric cells, using explicit
 * per-zoom sizes and opacities from config('source_raster.style').
 *
 * This is a port of the raster-lab worker so the published tiles match the
 * tuned profile: blue marks share one diameter and combine by source-over alpha;
 * condition colors keep their maximum coverage per color; complete color layers
 * are composited in the configured order. Output is written as 8-bit RGBA,
 * because GD's 7-bit alpha cannot represent the faint world-view blue.
 */
final class RasterRenderer
{
    public const SIZE = 512;

    // Neighbouring cells read around each tile; must cover the largest mark's
    // reach (stamp radius plus the one-pixel multicolor offset).
    public const GUTTER = 8;

    public const PIXEL_RATIO = 2;

    public const COLORS = [
        0 => [35, 143, 225],
        1 => [231, 164, 0],
        2 => [0, 145, 69],
        3 => [224, 48, 43],
    ];

    public const COLOR_NAMES = ['blue', 'yellow', 'green', 'red'];

    /** @var array{order: list<string>, zooms: array<int, array<string, array{0: float|int, 1: float|int}>>} */
    private array $style;

    public function __construct(?array $style = null)
    {
        $this->style = $style ?? config('source_raster.style');
    }

    /** @param iterable<array{px: int, py: int, status: int, n: int}> $cells */
    public function png(iterable $cells, int $zoom = 0): string
    {
        if (! function_exists('gzcompress')) {
            throw new RuntimeException('The zlib PHP extension is required for source raster tiles.');
        }

        $zoom = max(0, min(10, $zoom));
        $gutter = self::GUTTER;
        $stride = self::SIZE + $gutter * 2;

        $blue = $reports = [];
        foreach ($cells as $cell) {
            $x = (int) $cell['px'];
            $y = (int) $cell['py'];
            $status = (int) $cell['status'];
            if (! isset(self::COLORS[$status]) || $cell['n'] < 1
                || $x < -$gutter || $x >= self::SIZE + $gutter
                || $y < -$gutter || $y >= self::SIZE + $gutter) {
                continue;
            }
            $key = ($y + $gutter) * $stride + $x + $gutter;
            if ($status === 0) {
                // Coincident sources of every type share one mark.
                $blue[$key] = ($blue[$key] ?? 0) + (int) $cell['n'];
            } else {
                // Keep every reported category, even when it shares a bin with a majority.
                $reports[$key] = ($reports[$key] ?? 0) | (1 << $status);
            }
        }

        $styles = $this->styles($zoom);
        $planes = [[], [], [], []];

        // Blue alpha includes each mark's opacity; count n combines as 1 - (1 - opacity)^n,
        // then neighbouring marks accumulate by ordinary source-over.
        [$diameter, $sourceOpacity] = $styles[0];
        if ($diameter > 0 && $sourceOpacity > 0 && $blue !== []) {
            $stamp = self::stamp($diameter);
            $logTransparency = $sourceOpacity < 1 ? log1p(-$sourceOpacity) : -INF;
            $byCount = [];
            $ink = &$planes[0];
            foreach ($blue as $key => $count) {
                $opacity = $byCount[$count] ??= ($sourceOpacity >= 1 ? 1.0 : -expm1($count * $logTransparency));
                $x = $key % $stride;
                $y = intdiv($key, $stride);
                foreach ($stamp as [$dx, $dy, $coverage]) {
                    $px = $x + $dx;
                    $py = $y + $dy;
                    if ($px < 0 || $py < 0 || $px >= $stride || $py >= $stride) {
                        continue;
                    }
                    $destination = $py * $stride + $px;
                    $previous = $ink[$destination] ?? 0.0;
                    if ($previous >= 1) {
                        continue;
                    }
                    $ink[$destination] = $previous + $opacity * $coverage * (1 - $previous);
                }
            }
            unset($ink);
        }
        unset($blue);

        $stamps = [1 => self::stamp($styles[1][0]), 2 => self::stamp($styles[2][0]), 3 => self::stamp($styles[3][0])];
        foreach ($reports as $key => $presence) {
            $multiple = ($presence & ($presence - 1)) !== 0;
            $x = $key % $stride;
            $y = intdiv($key, $stride);
            foreach ([1, 2, 3] as $status) {
                if (($presence & (1 << $status)) === 0 || $styles[$status][0] <= 0 || $styles[$status][1] <= 0) {
                    continue;
                }
                $dx = $multiple ? ($status === 2 ? -1 : ($status === 3 ? 1 : 0)) : 0;
                $dy = $multiple ? ($status === 1 ? -1 : 1) : 0;
                $plane = &$planes[$status];
                foreach ($stamps[$status] as [$sx, $sy, $coverage]) {
                    $px = $x + $dx + $sx;
                    $py = $y + $dy + $sy;
                    if ($px < 0 || $py < 0 || $px >= $stride || $py >= $stride) {
                        continue;
                    }
                    $destination = $py * $stride + $px;
                    $strength = (int) round(127 * $coverage);
                    if ($strength > ($plane[$destination] ?? 0)) {
                        $plane[$destination] = $strength;
                    }
                }
                unset($plane);
            }
        }
        unset($reports);

        // Finish one complete layer at a time, so order applies across cell boundaries too.
        $red = $green = $blueChannel = $alpha = [];
        foreach ($this->order() as $status) {
            $opacity = $styles[$status][1];
            if ($opacity <= 0 || $planes[$status] === []) {
                continue;
            }
            [$cr, $cg, $cb] = self::COLORS[$status];
            foreach ($planes[$status] as $key => $strength) {
                if (! $strength) {
                    continue;
                }
                $x = $key % $stride - $gutter;
                $y = intdiv($key, $stride) - $gutter;
                if ($x < 0 || $y < 0 || $x >= self::SIZE || $y >= self::SIZE) {
                    continue;
                }
                $i = $y * self::SIZE + $x;
                $sourceAlpha = $status === 0 ? $strength : round($strength * 255 / 127) / 255 * $opacity;
                $previousAlpha = $alpha[$i] ?? 0;
                $remainder = $previousAlpha / 255 * (1 - $sourceAlpha);
                $output = $sourceAlpha + $remainder;
                $red[$i] = (int) round(($cr * $sourceAlpha + ($red[$i] ?? 0) * $remainder) / $output);
                $green[$i] = (int) round(($cg * $sourceAlpha + ($green[$i] ?? 0) * $remainder) / $output);
                $blueChannel[$i] = (int) round(($cb * $sourceAlpha + ($blueChannel[$i] ?? 0) * $remainder) / $output);
                $alpha[$i] = (int) round($output * 255);
            }
            $planes[$status] = [];
        }

        $row = self::SIZE * 4 + 1;
        $raw = str_repeat("\0", $row * self::SIZE);
        foreach ($alpha as $i => $value) {
            $offset = intdiv($i, self::SIZE) * $row + 1 + ($i % self::SIZE) * 4;
            $raw[$offset] = chr($red[$i]);
            $raw[$offset + 1] = chr($green[$i]);
            $raw[$offset + 2] = chr($blueChannel[$i]);
            $raw[$offset + 3] = chr($value);
        }

        return self::encode($raw, (int) config('source_raster.compression', 3));
    }

    /**
     * Physical diameter and opacity for blue, yellow, green and red at a zoom.
     *
     * @return array<int, array{0: float, 1: float}>
     */
    public function styles(int $zoom): array
    {
        $row = $this->style['zooms'][max(0, min(10, $zoom))] ?? [];

        return array_map(function (string $name) use ($row): array {
            [$size, $opacity] = $row[$name] ?? [0, 0];

            return [max(0.0, min(24.0, (float) $size)) * self::PIXEL_RATIO, max(0.0, min(1.0, (float) $opacity))];
        }, self::COLOR_NAMES);
    }

    /** Farthest pixel any configured mark can reach from its cell, including the multicolor offset. */
    public function reach(): int
    {
        $reach = 0;
        for ($zoom = 0; $zoom <= 10; $zoom++) {
            foreach ($this->styles($zoom) as $status => [$diameter]) {
                foreach (self::stamp($diameter) as [$x, $y]) {
                    $reach = max($reach, abs($x) + ($status > 0 ? 1 : 0), abs($y) + ($status > 0 ? 1 : 0));
                }
            }
        }

        return $reach;
    }

    /** Subpixel coverage, identical to the lab worker's stamp(). */
    private static function stamp(float $diameter): array
    {
        static $stamps = [];
        $key = (string) round($diameter, 3);
        if (isset($stamps[$key])) {
            return $stamps[$key];
        }
        if ($diameter <= 1) {
            return $stamps[$key] = $diameter > 0 ? [[0, 0, $diameter * $diameter]] : [];
        }
        $radius = $diameter / 2;
        $extent = (int) ceil($radius);
        $stamp = [];
        for ($y = -$extent; $y <= $extent; $y++) {
            for ($x = -$extent; $x <= $extent; $x++) {
                $hits = 0;
                for ($sy = 0; $sy < 8; $sy++) {
                    for ($sx = 0; $sx < 8; $sx++) {
                        $dx = $x + ($sx + 0.5) / 8 - 0.5;
                        $dy = $y + ($sy + 0.5) / 8 - 0.5;
                        $hits += (int) ($dx * $dx + $dy * $dy <= $radius * $radius);
                    }
                }
                if ($hits > 0) {
                    $stamp[] = [$x, $y, $hits / 64];
                }
            }
        }

        return $stamps[$key] = $stamp;
    }

    /** Minimal truecolor-with-alpha PNG; every scanline uses filter type 0. */
    private static function encode(string $raw, int $level): string
    {
        $chunk = static fn (string $type, string $data): string => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));

        return "\x89PNG\r\n\x1a\n"
            .$chunk('IHDR', pack('NNCCCCC', self::SIZE, self::SIZE, 8, 6, 0, 0, 0))
            .$chunk('IDAT', gzcompress($raw, max(0, min(9, $level))))
            .$chunk('IEND', '');
    }

    /** @return list<int> statuses bottom to top */
    private function order(): array
    {
        $order = [];
        foreach ($this->style['order'] ?? [] as $name) {
            $status = array_search($name, self::COLOR_NAMES, true);
            if ($status !== false && ! in_array($status, $order, true)) {
                $order[] = $status;
            }
        }
        foreach (array_keys(self::COLORS) as $status) {
            if (! in_array($status, $order, true)) {
                $order[] = $status;
            }
        }

        return $order;
    }
}
