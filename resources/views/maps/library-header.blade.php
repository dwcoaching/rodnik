<header class="grid gap-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="grid gap-1.5">
            <h1 class="text-2xl font-bold tracking-tight text-zinc-900 sm:text-3xl">{{ __($activeTab === 'tracks' ? 'ui.tracks.my_tracks' : 'ui.maps.my_maps') }}</h1>
            <p class="max-w-xl text-sm leading-6 text-zinc-500">{{ __($activeTab === 'tracks' ? 'ui.tracks.library_description' : 'ui.maps.library_description') }}</p>
        </div>
    </div>
    <nav aria-label="{{ __('ui.maps.library_navigation') }}" class="flex gap-6 border-b border-zinc-200 text-sm font-semibold">
        <a href="{{ localized_route('maps.index') }}" data-rodnik-navigate @if ($activeTab === 'maps') aria-current="page" @endif class="-mb-px border-b-2 px-1 pb-3 {{ $activeTab === 'maps' ? 'border-blue-600 text-blue-700' : 'border-transparent text-zinc-500 hover:border-zinc-300 hover:text-zinc-900' }} focus-visible:outline-2 focus-visible:outline-blue-600">{{ __('ui.maps.maps_tab') }}</a>
        <a href="{{ localized_route('tracks.index') }}" data-rodnik-navigate @if ($activeTab === 'tracks') aria-current="page" @endif class="-mb-px border-b-2 px-1 pb-3 {{ $activeTab === 'tracks' ? 'border-blue-600 text-blue-700' : 'border-transparent text-zinc-500 hover:border-zinc-300 hover:text-zinc-900' }} focus-visible:outline-2 focus-visible:outline-blue-600">{{ __('ui.maps.tracks_tab') }}</a>
    </nav>
</header>
