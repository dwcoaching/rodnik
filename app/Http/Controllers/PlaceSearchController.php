<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Geography\PlaceSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PlaceSearchController extends Controller
{
    // Larger views say little about which same-named village is meant.
    private const MAX_VIEW_DEGREES = 20.0;

    public function __invoke(Request $request, PlaceSearch $search): JsonResponse
    {
        $query = $request->query('q');
        $results = $search->search(is_string($query) ? $query : '', view: $this->view($request->query('bbox')), user: $request->user());

        return response()
            ->json($results, options: JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION)
            ->header('Cache-Control', 'private, max-age=300');
    }

    /** @return array{west: float, south: float, east: float, north: float}|null */
    private function view(mixed $bbox): ?array
    {
        if (! is_string($bbox) || preg_match('/^(-?\d{1,3}(?:\.\d+)?),(-?\d{1,2}(?:\.\d+)?),(-?\d{1,3}(?:\.\d+)?),(-?\d{1,2}(?:\.\d+)?)$/', $bbox, $matches) !== 1) {
            return null;
        }

        [$west, $south, $east, $north] = array_map(floatval(...), array_slice($matches, 1));
        $width = $east >= $west ? $east - $west : $east - $west + 360;

        if (max(abs($west), abs($east)) > 180 || max(abs($south), abs($north)) > 90 || $south >= $north
            || $width > self::MAX_VIEW_DEGREES || $north - $south > self::MAX_VIEW_DEGREES) {
            return null;
        }

        return ['west' => $west, 'south' => $south, 'east' => $east, 'north' => $north];
    }
}
