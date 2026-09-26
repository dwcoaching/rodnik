<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\DeleteTrackAction;
use App\Actions\RenameTrackAction;
use App\Actions\StoreTrackAction;
use App\Models\Track;
use App\Support\TrackGeometry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

final class TrackController extends Controller
{
    public function show(string $token): JsonResponse
    {
        $track = Track::query()->where('token', $token)->firstOrFail(['id', 'hash', 'token', 'name', 'track']);

        return response()->json($track->only(['id', 'hash', 'token', 'name', 'track']))->header('Cache-Control', 'no-store');
    }

    public function store(Request $request, StoreTrackAction $store): JsonResponse
    {
        $track = $store($request->user(), $request->only(['hash', 'track', 'name']));

        return response()->json($track->only(['id', 'hash', 'token', 'name']), $track->wasRecentlyCreated ? 201 : 200)
            ->header('Cache-Control', 'no-store');
    }

    public function options(Request $request): JsonResponse
    {
        $validated = $request->validate(['q' => ['nullable', 'string', 'max:160']]);
        $search = mb_trim($validated['q'] ?? '');
        $tracks = $this->libraryQuery($request)
            ->when($search !== '', fn (Builder $query): Builder => $query->whereLike('name', '%'.addcslashes($search, '%_\\').'%'))
            ->latest()->orderByDesc('id')->paginate(20)->withQueryString();

        return response()->json([
            'data' => $tracks->getCollection()->map(fn (Track $track): array => $track->ownerData()),
            'next_page_url' => $tracks->nextPageUrl(), 'current_page' => $tracks->currentPage(),
            'last_page' => $tracks->lastPage(), 'total' => $tracks->total(),
        ])->header('Cache-Control', 'no-store');
    }

    public function update(Request $request, Track $track, RenameTrackAction $rename): JsonResponse
    {
        $rename($request->user(), $track, $request->only('name'));
        $current = $this->libraryQuery($request)->whereKey($track->id)->firstOrFail();

        return response()->json($current->ownerData())->header('Cache-Control', 'no-store');
    }

    public function destroy(Request $request, Track $track, DeleteTrackAction $delete): Response
    {
        $delete($request->user(), $track);

        return response()->noContent();
    }

    public function download(Request $request, Track $track): Response
    {
        Gate::forUser($request->user())->authorize('update', $track);
        $filename = (Str::slug($track->name) ?: 'track').'.gpx';

        return response(TrackGeometry::gpx($track->track, $track->name))
            ->header('Content-Type', 'application/gpx+xml; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"')
            ->header('Cache-Control', 'no-store');
    }

    private function libraryQuery(Request $request): Builder
    {
        return Track::query()->whereBelongsTo($request->user())
            ->select(['id', 'token', 'user_id', 'name', 'summary', 'created_at'])
            ->withCount('maps')
            ->with(['maps' => fn (HasMany $query): HasMany => $query->whereBelongsTo($request->user())->select(['id', 'track_id', 'title'])]);
    }
}
