<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\SourceRaster\RasterPyramid;
use App\Services\SourceRaster\RasterRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use InvalidArgumentException;

/** Admin tuning of the source overview: read the style, preview a draft, queue a redraw. */
final class SourceRasterStyleController extends Controller
{
    public function show(RasterPyramid $pyramid): JsonResponse
    {
        $manifest = $pyramid->manifest();

        return response()->json([
            // The lab reads tiles from here, so it also works while visitors get the vector overview.
            'url' => $manifest['url'] ?? null,
            'max_zoom' => $manifest['max_zoom'] ?? null,
            'style' => $pyramid->style(),
            'pending_style' => $pyramid->pendingStyle(),
            'max_diameter' => RasterRenderer::MAX_DIAMETER,
            ...$pyramid->status(),
        ])->header('Cache-Control', 'no-store');
    }

    /** One tile drawn with a draft style for its zoom; nothing is stored. */
    public function preview(Request $request, RasterPyramid $pyramid, int $z, int $x, int $y): Response
    {
        // "s" is size,opacity for blue, yellow, green, red; "o" is the draw order as indexes.
        $values = array_map('floatval', explode(',', (string) $request->query('s')));
        $order = array_map('intval', mb_str_split((string) $request->query('o', '0123')));
        abort_unless(count($values) === 8 && $z >= 0 && $z <= 10, 422);

        try {
            $style = $pyramid->style();
            $style['order'] = array_map(fn (int $index): string => RasterRenderer::COLOR_NAMES[$index] ?? '', $order);
            foreach (RasterRenderer::COLOR_NAMES as $index => $name) {
                $style['zooms'][$z][$name] = [$values[$index * 2], $values[$index * 2 + 1]];
            }
            $png = $pyramid->png($z, $x, $y, RasterRenderer::normalizeStyle($style));
        } catch (InvalidArgumentException $exception) {
            abort(422, $exception->getMessage());
        }

        return response($png, 200, ['Content-Type' => 'image/png', 'Cache-Control' => 'private, max-age=600']);
    }

    /** Save a complete style; the per-minute refresh redraws every tile with it. */
    public function store(Request $request, RasterPyramid $pyramid): JsonResponse
    {
        abort_if($pyramid->manifest() === null, 409, 'Build the source overview first.');

        try {
            $pyramid->queueStyle((array) $request->input('style'));
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json($pyramid->status(), 202);
    }
}
