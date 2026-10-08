{{-- Contents of a map card or row; the parent is `group relative` and the title link covers it. --}}
@php
    $mapUrl = $map->publicData(null)['url'];
    $track = \App\Support\Docs\PublicMaps::listsTrack($map) ? $map->track : null;
    $previewPath = \App\Support\Docs\RoutePreview::path($track?->summary['preview'] ?? null, 50);
    $mapCenter = $track ? '' : \App\Support\Docs\RoutePreview::coordinates($map->state['center'] ?? null);
@endphp
<div class="w-20 shrink-0 self-center overflow-hidden rounded-lg border border-blue-100 bg-blue-50/50">
    @include('docs.map-preview', ['path' => $previewPath])
</div>
<div class="grid min-w-0 flex-1 grid-cols-1 content-center gap-0.5">
    <{{ $heading }} class="text-base leading-6 font-semibold">
        <a href="{{ $mapUrl }}" data-rodnik-navigate data-rodnik-exact-url class="wrap-anywhere text-zinc-900 transition-colors group-hover:text-blue-700 after:absolute after:inset-0 after:rounded-xl focus-visible:outline-none focus-visible:after:outline-2 focus-visible:after:-outline-offset-2 focus-visible:after:outline-blue-600">{{ $map->title }}</a>
    </{{ $heading }}>
    <p class="flex min-w-0 flex-wrap items-center gap-x-1.5 text-sm leading-5 text-zinc-600">
        @if ($map->user)
            <a href="{{ duo_route(['user' => $map->user->id]) }}" data-rodnik-navigate class="relative z-10 min-w-0 truncate rounded-sm font-medium text-zinc-700 hover:text-blue-700 hover:underline focus-visible:outline-2 focus-visible:outline-blue-600">{{ $map->user->name }}</a>
            <span aria-hidden="true">·</span>
        @endif
        @if ($map->updated_at)
            <time datetime="{{ $map->updated_at->toIso8601String() }}" title="{{ __('pages.maps.updated') }}">{{ $map->updated_at->isoFormat('ll') }}</time>
        @endif
    </p>
    @if ($track)
        <p class="truncate text-sm leading-5 text-zinc-500">{{ __('pages.maps.track', ['name' => $track->name]) }}</p>
    @elseif ($mapCenter !== '')
        <p class="text-sm leading-5 text-zinc-500">{{ $mapCenter }}</p>
    @endif
</div>
