<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\StoreTrackPolygonAction;
use App\Models\TrackPolygon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TrackPolygonController extends Controller
{
    public function show(string $hash): JsonResponse
    {
        $polygon = TrackPolygon::query()->where('hash', $hash)->firstOrFail(['id', 'hash']);

        return response()->json($polygon->only(['id', 'hash']))->header('Cache-Control', 'no-store');
    }

    public function store(Request $request, StoreTrackPolygonAction $store): JsonResponse
    {
        $polygon = $store($request->user(), $request->only(['hash', 'polygon']));

        return response()->json($polygon->only(['id', 'hash']), $polygon->wasRecentlyCreated ? 201 : 200);
    }
}
