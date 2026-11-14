<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\TrackPolygon;
use App\Services\SourceRaster\RasterPyramid;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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

        return response()->json($manifest)->header('Cache-Control', 'no-store');
    }

    public function show(Request $request, RasterPyramid $pyramid, string $revision, int $z, int $x, int $y): BinaryFileResponse
    {
        abort_if($z > 10 || $x >= 2 ** $z || $y >= 2 ** $z, 404);

        $validator = Validator::make($request->query(), [
            'types' => ['sometimes', 'integer', 'between:0,63'],
            'reports' => ['sometimes', 'boolean'],
            'polygon' => ['sometimes', 'nullable', 'string', 'regex:/\A[a-f0-9]{64}\z/'],
        ]);
        if ($validator->fails()) {
            // These public asset requests have no session, including when a browser
            // requests an invalid PNG URL without an Accept: application/json header.
            throw new ValidationException($validator, response()->json([
                'message' => 'Invalid source overview filters.',
                'errors' => $validator->errors(),
            ], 422));
        }
        $filters = $validator->validated();
        $polygon = null;

        if (! empty($filters['polygon'])) {
            // Polygon hashes are public capabilities, as in TrackPolygonController::show.
            // Return only rendered sources; the private geometry file is never served.
            $trackPolygon = TrackPolygon::query()->where('hash', $filters['polygon'])->firstOrFail();
            abort_unless(Storage::disk(TrackPolygon::GEOMETRY_DISK)->exists($trackPolygon->geometryPath()), 404);
            $polygon = $trackPolygon->polygon;
        }

        try {
            $path = $pyramid->tile(
                $revision, $z, $x, $y,
                (int) ($filters['types'] ?? 63),
                $request->boolean('reports'),
                $polygon,
            );
        } catch (InvalidArgumentException) {
            abort(404);
        }

        abort_unless(is_file($path), 404);

        return response()->file($path, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
