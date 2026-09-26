<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\DeleteMapAction;
use App\Actions\RenameMapAction;
use App\Actions\StarMapAction;
use App\Actions\StoreMapAction;
use App\Actions\UpdateMapAction;
use App\Actions\UpdateMapDetailsAction;
use App\Models\Map;
use App\Models\Spring;
use App\Models\User;
use App\Support\DuoUrl;
use App\Support\MapDetailsPayload;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

final class MapController extends Controller
{
    public function login(Request $request, Map $map): RedirectResponse
    {
        $destination = $map->publicData($request->user())['url'];

        if ($request->user()) {
            return redirect($destination);
        }

        $request->session()->put('url.intended', $destination);

        return redirect()->route('login');
    }

    public function index(Request $request): Response
    {
        return $this->managementPage($request);
    }

    public function edit(Request $request, Map $map): Response
    {
        Gate::forUser($request->user())->authorize('update', $map);

        return $this->managementPage($request, $map);
    }

    public function checkSlug(Request $request, MapDetailsPayload $payload): JsonResponse
    {
        $validated = $request->validate(['map' => ['bail', 'nullable', 'integer', 'min:1']]);
        $map = isset($validated['map'])
            ? $request->user()->maps()->whereKey($validated['map'])->firstOrFail()
            : null;
        $payload->validateSlug($request->input('slug'), $map);

        return response()->json(['available' => true])->header('Cache-Control', 'no-store');
    }

    public function options(Request $request): JsonResponse
    {
        $filters = $this->managementFilters($request);
        $maps = $this->managementQuery($request, $filters)->paginate(20)->withQueryString();

        return response()->json([
            'data' => $maps->getCollection()->map(fn (Map $map): array => $map->ownerData($request->user(), $this->sharingLocale($request))),
            'next_page_url' => $maps->nextPageUrl(),
            'current_page' => $maps->currentPage(),
            'last_page' => $maps->lastPage(),
            'total' => $maps->total(),
        ])->header('Cache-Control', 'no-store');
    }

    public function show(Request $request, Map $map, DuoUrl $duoUrl): Response|JsonResponse
    {
        $sharedMap = $map->publicData($request->user());
        $page = $sharedMap['state']['page'];
        $spring = $page['spring'] === null ? null : Spring::query()->find($page['spring']);
        $user = $page['user'] === null ? null : User::query()->find($page['user']);

        if ($page['spring'] !== null && $spring === null) {
            $page['spring'] = null;
            $page['location'] = null;
        }

        if ($page['user'] !== null && $user === null) {
            $page['user'] = null;
        }

        $request->attributes->set('duo.page', $page);
        $request->attributes->set('duo.spring', $spring);
        $request->attributes->set('duo.user', $user);

        $query = $request->query();
        unset($query['redirect']);
        if ($spring?->redirect_to_spring_id) {
            $query['redirect'] = 'false';
        }

        $sharedMap['state']['page'] = $page;
        $sharedMap['resource_url'] = $duoUrl->absolute($page, query: $query);

        if ($request->expectsJson()) {
            return response()->json($sharedMap)
                ->header('Cache-Control', 'no-store')
                ->header('X-Robots-Tag', 'noindex');
        }

        $response = response()->view('duo', ['sharedMap' => $sharedMap])
            ->header('Cache-Control', 'no-store')
            ->header('X-Robots-Tag', 'noindex');

        if ($request->isMethod('GET')) {
            Map::query()->whereKey($map->id)->toBase()->increment('views_count');
        }

        return $response;
    }

    public function missing(Request $request): Response|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => __('ui.maps.not_found')], 404)
                ->header('Cache-Control', 'no-store')
                ->header('X-Robots-Tag', 'noindex');
        }

        $page = ['spring' => null, 'user' => null, 'location' => null];
        $request->attributes->set('duo.page', $page);
        $request->attributes->set('duo.spring', null);
        $request->attributes->set('duo.user', null);

        return response()->view('duo', ['page' => $page, 'sharedMap' => null, 'missingMap' => true], 404)
            ->header('Cache-Control', 'no-store')
            ->header('X-Robots-Tag', 'noindex');
    }

    public function store(Request $request, StoreMapAction $store): JsonResponse
    {
        $map = $store($request->user(), $request->only(['title', 'slug', 'state', 'track_token']), $this->sharingLocale($request));

        return response()->json($map->ownerData($request->user(), $this->sharingLocale($request)), 201)->header('Cache-Control', 'no-store');
    }

    public function update(Request $request, Map $map, UpdateMapAction $update): JsonResponse
    {
        $map = $update($request->user(), $map, $request->only(['title', 'state', 'track_token', 'version']), $this->sharingLocale($request));

        return response()->json($map->ownerData($request->user(), $this->sharingLocale($request)))->header('Cache-Control', 'no-store');
    }

    public function rename(Request $request, Map $map, RenameMapAction $rename): JsonResponse
    {
        $map = $rename($request->user(), $map, $request->only(['title', 'version']), $this->sharingLocale($request));

        return response()->json([
            'title' => $map->title,
            'version' => $map->version,
            'updated_at' => $map->updated_at->toISOString(),
        ])->header('Cache-Control', 'no-store');
    }

    public function details(Request $request, Map $map, UpdateMapDetailsAction $update): JsonResponse
    {
        $map = $update($request->user(), $map, $request->only(['title', 'slug', 'state', 'track_token', 'version']), $this->sharingLocale($request));

        return response()->json($map->ownerData($request->user(), $this->sharingLocale($request)))->header('Cache-Control', 'no-store');
    }

    public function star(Request $request, Map $map, StarMapAction $star): JsonResponse
    {
        $map = $star($request->user(), $map, $request->only('starred'));

        return response()->json($map->ownerData($request->user(), $this->sharingLocale($request)))->header('Cache-Control', 'no-store');
    }

    public function destroy(Request $request, Map $map, DeleteMapAction $delete): Response
    {
        $delete($request->user(), $map);

        return response()->noContent();
    }

    private function managementPage(Request $request, ?Map $editingMap = null): Response
    {
        $filters = $this->managementFilters($request);
        $maps = $this->managementQuery($request, $filters)->paginate(20)->withQueryString();

        return response()->view('maps.index', ['maps' => $maps, ...$filters, 'editingMap' => $editingMap, 'activeTab' => $request->routeIs('tracks.index', 'ru.tracks.index') ? 'tracks' : 'maps'])
            ->header('Cache-Control', 'no-store')
            ->header('X-Robots-Tag', 'noindex');
    }

    /** @return array{search: string, favorites: bool} */
    private function managementFilters(Request $request): array
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:160'],
            'favorites' => ['sometimes', 'boolean'],
        ]);

        return ['search' => mb_trim($validated['q'] ?? ''), 'favorites' => (bool) ($validated['favorites'] ?? false)];
    }

    /** @param array{search: string, favorites: bool} $filters */
    private function managementQuery(Request $request, array $filters): Builder
    {
        return Map::query()->with('track:id,token,hash,name,summary')->whereBelongsTo($request->user(), 'user')
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $this->filterSearch($query, $filters['search']);
            })
            ->when($filters['favorites'], fn (Builder $query): Builder => $query->where('is_starred', true))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->select(['id', 'user_id', 'slug', 'title', 'state', 'track_id', 'is_starred', 'version', 'created_at', 'updated_at']);
    }

    private function sharingLocale(Request $request): ?string
    {
        $locale = $request->header('X-Rodnik-Locale');

        return is_string($locale) && array_key_exists($locale, config('localization.supported')) ? $locale : null;
    }

    private function filterSearch(Builder $query, string $search): void
    {
        $pattern = '%'.addcslashes($search, '%_\\').'%';
        $query->where(function (Builder $query) use ($pattern, $search): void {
            $query->whereLike('title', $pattern);
            if (Str::isAscii($search)) {
                $query->orWhereLike('slug', $pattern);
            }
        });
    }
}
