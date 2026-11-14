<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\SourceRaster\RasterPyramid;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
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

    public function show(Request $request, RasterPyramid $pyramid, string $revision, int $z, int $x, int $y): Response
    {
        abort_if($z > 10 || $x >= 2 ** $z || $y >= 2 ** $z, 404);

        $validator = Validator::make($request->query(), [
            'types' => ['sometimes', 'integer', 'between:0,63'],
            'reports' => ['sometimes', 'boolean'],
        ]);
        if ($validator->fails()) {
            // These public asset requests have no session, including when a browser
            // requests an invalid PNG URL without an Accept: application/json header.
            throw new ValidationException($validator, response()->json([
                'message' => 'Invalid source overview filters.',
                'errors' => $validator->errors(),
            ], 422));
        }
        $types = (int) ($validator->validated()['types'] ?? 63);
        $reports = $request->boolean('reports');

        try {
            if ($types !== 63 || $reports) {
                // Filtered tiles are drawn per request and never stored, so the
                // number of filter combinations cannot fill the disk.
                abort_unless($revision === ($pyramid->manifest()['revision'] ?? null), 404);

                return response($pyramid->png($z, $x, $y, $types, $reports), 200, [
                    'Content-Type' => 'image/png',
                    'Cache-Control' => 'public, max-age=300',
                    'X-Content-Type-Options' => 'nosniff',
                ]);
            }
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
}
