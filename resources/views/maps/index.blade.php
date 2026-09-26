@section('title', __(($activeTab ?? 'maps') === 'tracks' ? 'ui.tracks.my_tracks' : 'ui.maps.my_maps').' — Rodnik.today')

@php
    $libraryRecord = fn ($map) => \Illuminate\Support\Arr::only($map->ownerData(auth()->user()), [
        'id', 'title', 'slug', 'url', 'version', 'starred', 'created_at', 'edit_url', 'details_url', 'star_url', 'delete_url', 'track_name', 'preview', 'center', 'updated_at',
    ]);
    $libraryConfig = [
        'endpoint' => localized_route('maps.options'),
        'slugEndpoint' => localized_route('maps.check-slug'),
        'indexUrl' => localized_route('maps.index'),
        'items' => $maps->getCollection()->map($libraryRecord)->values(),
        'query' => $search,
        'favorites' => $favorites,
        'nextPage' => $maps->hasMorePages() ? localized_route('maps.options', array_filter(['q' => $search, 'favorites' => $favorites ? 1 : null, 'page' => $maps->currentPage() + 1], fn ($value) => $value !== '' && $value !== null)) : null,
        'total' => $maps->total(),
        'currentPage' => $maps->currentPage(),
        'editingRecord' => $editingMap ? $libraryRecord($editingMap) : null,
        'csrfToken' => csrf_token(),
        'locale' => app()->getLocale(),
        'loadMessage' => __('ui.maps.load_failed'),
        'sessionMessage' => __('ui.maps.session_expired'),
        'rateLimitMessage' => __('ui.maps.rate_limit'),
        'messages' => [
            'titleRequiredMessage' => __('ui.maps.title_required'),
            'slugCheckingMessage' => __('ui.maps.link_checking'),
            'slugAvailableMessage' => __('ui.maps.link_available'),
            'slugInvalidMessage' => __('ui.maps.link_invalid'),
            'slugUnavailableMessage' => __('ui.maps.link_unavailable'),
            'slugCheckFailedMessage' => __('ui.maps.link_check_failed'),
            'sessionMessage' => __('ui.maps.session_expired'),
            'rateLimitMessage' => __('ui.maps.rate_limit'),
            'conflictMessage' => __('ui.maps.metadata_conflict'),
            'errorMessage' => __('ui.maps.save_failed'),
            'starMessage' => __('ui.maps.star_failed'),
            'deleteMessage' => __('ui.maps.delete_failed'),
            'copyMessage' => __('ui.maps.copy_failed'),
        ],
    ];
@endphp

<x-app-layout navbar>
    @if (($activeTab ?? 'maps') === 'tracks')
        @include('maps.tracks')
    @else
    <main id="map-library" x-data="mapLibrary(@js($libraryConfig))" class="mx-auto grid w-full max-w-5xl gap-6 px-4 py-8 font-sans sm:px-6 sm:py-12">
        @include('maps.library-header', ['activeTab' => 'maps'])
        <section aria-label="{{ __('ui.maps.my_maps') }}" class="min-w-0 rounded-xl border border-zinc-200 bg-white shadow-xs">
            <div role="search" class="flex flex-wrap items-center justify-between gap-3 border-b border-zinc-200 p-4 sm:px-5">
                <div class="relative min-w-0 flex-1 basis-48 sm:max-w-xs">
                    <label for="map-search" class="sr-only">{{ __('ui.maps.search_library') }}</label>
                    <svg class="pointer-events-none absolute top-3 left-3 size-4 text-zinc-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5" /><path stroke-linecap="round" d="m16 16 4 4" /></svg>
                    <input id="map-search" x-ref="search" x-model="query" @input="scheduleSearch()" name="q" type="search" maxlength="160" placeholder="{{ __('ui.maps.search_library') }}" class="min-h-10 w-full rounded-lg border-zinc-200 bg-white py-2 pr-3 pl-9 text-base text-zinc-900 placeholder:text-zinc-400 focus:border-blue-500 focus:ring-blue-500 sm:text-sm" />
                </div>
                <div id="map-favorites" role="group" aria-label="{{ __('ui.maps.filter_maps') }}" class="inline-flex shrink-0 gap-0.5 rounded-lg bg-zinc-100 p-1 text-sm font-medium">
                    <button type="button" @click="favorites = false; filterChanged()" :aria-pressed="!favorites" :class="!favorites ? 'bg-white text-zinc-900 shadow-xs' : 'text-zinc-500 hover:text-zinc-900'" class="min-h-8 rounded-md px-3 transition-colors focus-visible:outline-2 focus-visible:outline-blue-600">{{ __('ui.maps.all_maps') }}</button>
                    <button type="button" @click="favorites = true; filterChanged()" :aria-pressed="favorites" :class="favorites ? 'bg-white text-zinc-900 shadow-xs' : 'text-zinc-500 hover:text-zinc-900'" class="min-h-8 rounded-md px-3 transition-colors focus-visible:outline-2 focus-visible:outline-blue-600">{{ __('ui.maps.favorites') }}</button>
                </div>
            </div>
            <div x-cloak x-show="loading || error" class="border-b border-zinc-100 px-4 py-3 sm:px-5">
                <p x-show="loading" role="status" class="flex items-center gap-2 text-sm text-zinc-500"><span aria-hidden="true" class="size-4 animate-spin rounded-full border-2 border-zinc-200 border-t-blue-600"></span>{{ __('ui.maps.loading_maps') }}</p>
                <div x-show="error" role="alert" class="flex flex-wrap items-center gap-3 text-sm text-red-700">
                    <p x-text="error"></p><button type="button" @click="load(retryAppend, retryPreserveWindow)" :disabled="loading" class="min-h-10 rounded-sm font-medium underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-blue-600 disabled:opacity-50">{{ __('ui.maps.retry') }}</button>
                </div>
            </div>
            <div id="map-library-list" :aria-busy="loading" class="divide-y divide-zinc-100">
                <template x-for="item in items" :key="item.id">
                    <article x-data="mapTitles(rowConfig(item))" x-effect="receive(item)" :aria-busy="busy" class="relative flex items-start gap-2 px-3 py-3 sm:gap-4 sm:px-5">
                        <div class="grid min-w-0 flex-1 gap-0.5">
                            <div class="min-w-0 pr-20 sm:pr-0" data-map-title>
                                <a :href="url" x-text="title" data-rodnik-navigate data-rodnik-exact-url class="rounded-sm text-base leading-6 font-semibold wrap-anywhere text-zinc-900 hover:text-blue-700 focus-visible:outline-2 focus-visible:outline-blue-600"></a>
                            </div>
                            <button type="button" @click="copy()" :disabled="!canCopyLink()" :aria-label="copied ? @js(__('ui.maps.copied')) : @js(__('ui.maps.copy_link'))" :title="@js(__('ui.maps.copy_link')) + ': ' + url" :class="copied ? 'bg-blue-50 text-blue-700' : 'bg-transparent text-zinc-500 hover:bg-blue-50 hover:text-blue-700'" class="-ml-2 inline-flex h-6 w-fit min-w-0 max-w-full cursor-pointer items-center gap-1.5 justify-self-start rounded-md px-2 text-left font-normal transition-colors focus-visible:bg-blue-50 focus-visible:text-blue-700 focus-visible:outline-2 focus-visible:outline-blue-600 disabled:opacity-50" data-map-link>
                                <span x-text="url" class="min-w-0 truncate text-sm leading-5" data-map-url></span>
                                <svg x-show="!copied" class="size-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="8" y="8" width="12" height="12" rx="2" /><path stroke-linecap="round" d="M16 8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h3" /></svg>
                                <svg x-cloak x-show="copied" class="size-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6" /></svg>
                                <span x-cloak x-show="copied" class="shrink-0 text-sm font-semibold" data-map-copied>{{ __('ui.maps.copied') }}</span>
                            </button>
                            <p role="status" aria-live="polite" class="sr-only" x-text="copied ? @js(__('ui.maps.copied_message')) : saved ? @js(__('ui.maps.details_saved')) : ''"></p>
                            <p :id="'map-row-error-' + id" x-cloak x-show="error" x-text="error" role="alert" class="text-sm text-red-700"></p>
                            <input x-cloak x-show="manualCopyUrl" x-ref="link" :value="manualCopyUrl" readonly @click="$el.select()" aria-label="{{ __('ui.maps.link') }}" class="min-h-10 w-full rounded-lg border-zinc-200 text-base text-zinc-900 focus:border-blue-500 focus:ring-blue-500 sm:text-sm" />
                        </div>
                        <div class="absolute top-2 right-2 flex shrink-0 items-center gap-0.5 self-start sm:static" data-map-actions>
                            <button type="button" @click="toggleStar()" :disabled="busy" :aria-pressed="starred" :aria-label="starred ? @js(__('ui.maps.unstar_map')) : @js(__('ui.maps.star_map'))" :title="starred ? @js(__('ui.maps.unstar_map')) : @js(__('ui.maps.star_map'))" :class="starred ? 'text-amber-500 hover:bg-amber-50' : 'text-zinc-400 hover:bg-zinc-100 hover:text-amber-500'" class="inline-flex size-8 items-center justify-center rounded-lg sm:size-9 transition-colors focus-visible:outline-2 focus-visible:outline-blue-600 disabled:opacity-50">
                                <svg class="size-4" viewBox="0 0 24 24" :fill="starred ? 'currentColor' : 'none'" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m12 3 2.78 5.64 6.22.9-4.5 4.39 1.06 6.2L12 17.2l-5.56 2.93 1.06-6.2L3 9.54l6.22-.9L12 3Z" /></svg>
                            </button>
                            <div class="relative" @click.outside="menuOpen = false" @keydown.escape.stop.prevent="menuOpen = false; $refs.edit.focus()">
                                <button type="button" x-ref="edit" @click="menuOpen = !menuOpen" :disabled="busy" :aria-expanded="menuOpen" aria-haspopup="true" :aria-controls="'map-actions-' + id" aria-label="{{ __('ui.maps.more_actions') }}" class="inline-flex size-8 items-center justify-center rounded-lg sm:size-9 text-zinc-500 hover:bg-zinc-100 focus-visible:outline-2 focus-visible:outline-blue-600 disabled:opacity-50"><svg class="size-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="5" cy="12" r="1.5" /><circle cx="12" cy="12" r="1.5" /><circle cx="19" cy="12" r="1.5" /></svg></button>
                                <div x-cloak x-show="menuOpen" :id="'map-actions-' + id" class="absolute top-full right-0 z-20 mt-1 grid w-44 gap-0.5 rounded-lg border border-zinc-200 bg-white p-1 shadow-lg">
                                    <button type="button" @click="menuOpen = false; openEdit(item, $refs.edit)" :disabled="busy" class="min-h-10 rounded-md px-3 text-left text-sm text-zinc-700 hover:bg-zinc-50 focus-visible:bg-zinc-50 focus-visible:outline-none">{{ __('ui.common.edit') }}</button>
                                    <div class="my-1 border-t border-zinc-100"></div>
                                    <button type="button" @click="menuOpen = false; !busy && openDelete(item, $refs.edit)" :disabled="busy" aria-haspopup="dialog" aria-controls="map-delete-dialog" class="min-h-10 rounded-md px-3 text-left text-sm text-red-700 hover:bg-red-50 focus-visible:bg-red-50 focus-visible:outline-none">{{ __('ui.maps.delete_map') }}</button>
                                </div>
                            </div>
                        </div>
                    </article>
                </template>
                <div id="map-library-empty" x-cloak x-show="!items.length && !loading && !error" class="flex flex-col items-center gap-3 px-6 py-16 text-center">
                    <div class="mb-1 flex size-12 items-center justify-center rounded-xl border border-zinc-200 bg-zinc-50 text-zinc-500"><svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m9 18-6 3V6l6-3 6 3 6-3v15l-6 3-6-3Zm0 0V3m6 18V6" /></svg></div>
                    <div class="grid gap-1.5"><h2 x-text="query.trim() ? @js(__('ui.maps.no_search_results')) : favorites ? @js(__('ui.maps.no_favorites')) : @js(__('ui.maps.empty'))" class="text-base font-semibold text-zinc-900"></h2><p x-text="query.trim() ? @js(__('ui.maps.no_search_results_help')) : favorites ? @js(__('ui.maps.no_favorites_help')) : @js(__('ui.maps.empty_description'))" class="mx-auto max-w-sm text-sm leading-6 text-zinc-500"></p></div>
                    <button x-show="query.trim()" type="button" @click="query = ''; filterChanged(); $refs.search.focus()" class="map-button-secondary mt-2">{{ __('ui.maps.clear_search') }}</button>
                </div>
            </div>
            <div x-cloak x-show="nextPage" class="flex justify-center border-t border-zinc-100 px-4 py-4"><button type="button" @click="load(true)" :disabled="loading" class="map-button-secondary">{{ __('ui.maps.more_maps') }}</button></div>
        </section>
        <template x-if="editingRecord">
            <div x-data="mapTitles(editConfig(editingRecord))">
                @include('maps.form')
            </div>
        </template>
        <template x-if="deletingRecord">
            <div x-data="mapTitles(deleteConfig(deletingRecord))">
                <template x-teleport="body">
                    <div x-cloak x-show="confirmingDelete" x-trap.inert.noscroll="confirmingDelete" @click.self="cancel()" @keydown.escape.stop.prevent="cancel()" class="fixed inset-0 z-90 flex items-center justify-center bg-zinc-950/40 p-4 font-sans">
                        <section id="map-delete-dialog" role="dialog" aria-modal="true" aria-labelledby="map-delete-heading" aria-describedby="map-delete-help" :aria-busy="busy" class="grid max-h-[90dvh] w-full max-w-md gap-5 overflow-y-auto rounded-xl border border-zinc-200 bg-white p-5 shadow-xl sm:p-6">
                            <div class="grid gap-2">
                                <h2 id="map-delete-heading" class="text-lg font-semibold tracking-tight text-zinc-900">{{ __('ui.maps.delete_confirm') }}</h2>
                                <p x-text="title" class="wrap-anywhere text-sm font-medium text-zinc-700"></p>
                                <p id="map-delete-help" class="text-sm leading-6 text-zinc-500">{{ __('ui.maps.delete_help') }}</p>
                            </div>
                            <p x-cloak x-show="error" x-text="error" role="alert" class="text-sm text-red-700"></p>
                            <div class="flex justify-end gap-2 text-sm">
                                <button type="button" x-ref="cancelDelete" @click="cancel()" :disabled="busy" class="map-button-secondary">{{ __('ui.common.cancel') }}</button>
                                <button type="button" @click="remove()" :disabled="busy" class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg bg-red-700 px-4 py-2 font-medium text-white hover:bg-red-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700 disabled:opacity-50">
                                    <span x-cloak x-show="busy" aria-hidden="true" class="size-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
                                    {{ __('ui.common.delete') }}
                                </button>
                            </div>
                        </section>
                    </div>
                </template>
            </div>
        </template>
    </main>
    @endif
</x-app-layout>
