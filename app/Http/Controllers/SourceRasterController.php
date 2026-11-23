<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\SourceRaster\RasterPyramid;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

final class SourceRasterController extends Controller
{
    public function index(RasterPyramid $pyramid): JsonResponse
    {
        $manifest = $pyramid->manifest();

        if ($manifest === null) {
            return response()->json(['message' => 'The source overview has not been built yet.'], 503)
                ->header('Cache-Control', 'no-store')
                ->header('Retry-After', '60');
        }

        if (! $pyramid->enabled()) {
            // Built but switched off in the admin: the map keeps its vector overview.
            return response()->json(['message' => 'The source overview is switched off.'], 503)
                ->header('Cache-Control', 'no-store')
                ->header('Retry-After', '60');
        }

        return response()->json($manifest)->header('Cache-Control', 'no-store');
    }

    public function current(RasterPyramid $pyramid, int $z, int $x, int $y): Response
    {
        $this->validateCoordinates($z, $x, $y);
        $revision = $pyramid->manifest()['revision'] ?? null;
        abort_if($revision === null || ! $pyramid->enabled(), 503, 'The source overview is unavailable.', [
            'Cache-Control' => 'no-store',
            'Retry-After' => '60',
        ]);

        return redirect()->route('source-raster.tile', compact('revision', 'z', 'x', 'y'))
            ->header('Cache-Control', 'no-store');
    }

    public function show(Request $request, RasterPyramid $pyramid, string $revision, int $z, int $x, int $y): Response
    {
        $this->validateCoordinates($z, $x, $y);

        try {
            $path = $pyramid->tile($revision, $z, $x, $y);
        } catch (InvalidArgumentException) {
            abort(404);
        }

        // A source edit rewrites its tiles in place, so browsers revalidate after a minute.
        $response = response()->file($path, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=60',
            'X-Content-Type-Options' => 'nosniff',
        ]);
        $response->isNotModified($request);

        return $response;
    }

    private function validateCoordinates(int $z, int $x, int $y): void
    {
        abort_if($z > 10 || $x >= 2 ** $z || $y >= 2 ** $z, 404);
    }
}
