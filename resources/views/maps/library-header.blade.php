<header class="grid gap-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="grid w-full min-w-0 gap-1.5">
            <h1 class="text-2xl font-bold tracking-tight text-zinc-900 sm:text-3xl">{{ __($activeTab === 'tracks' ? 'ui.tracks.my_tracks' : 'ui.maps.my_maps') }}</h1>
            @if ($activeTab === 'maps')
                @php([$beforeShare, $afterShare] = explode(':share', __('ui.maps.library_edit_help'), 2))
                <p id="map-library-help" class="text-sm leading-6 text-zinc-500">{{ $beforeShare }}<span class="mx-1 inline-flex items-center gap-2 whitespace-nowrap align-middle font-bold text-black">
                        <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71m2.25 5.82a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71" /></svg>
                        <span>{{ __('ui.maps.share') }}</span><span class="size-2 shrink-0 rounded-full bg-blue-600" aria-hidden="true"></span></span>{{ $afterShare }}</p>
            @else
                <p class="max-w-xl text-sm leading-6 text-zinc-500">{{ __('ui.tracks.library_description') }}</p>
            @endif
        </div>
    </div>
    <nav aria-label="{{ __('ui.maps.library_navigation') }}" class="flex gap-6 border-b border-zinc-200 text-sm font-semibold">
        <a href="{{ localized_route('maps.index') }}" data-rodnik-navigate @if ($activeTab === 'maps') aria-current="page" @endif class="-mb-px border-b-2 px-1 pb-3 {{ $activeTab === 'maps' ? 'border-blue-600 text-blue-700' : 'border-transparent text-zinc-500 hover:border-zinc-300 hover:text-zinc-900' }} focus-visible:outline-2 focus-visible:outline-blue-600">{{ __('ui.maps.maps_tab') }}</a>
        <a href="{{ localized_route('tracks.index') }}" data-rodnik-navigate @if ($activeTab === 'tracks') aria-current="page" @endif class="-mb-px border-b-2 px-1 pb-3 {{ $activeTab === 'tracks' ? 'border-blue-600 text-blue-700' : 'border-transparent text-zinc-500 hover:border-zinc-300 hover:text-zinc-900' }} focus-visible:outline-2 focus-visible:outline-blue-600">{{ __('ui.maps.tracks_tab') }}</a>
    </nav>
</header>
