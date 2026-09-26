@php
    $trackLibraryConfig = [
        'endpoint' => localized_route('tracks.options'),
        'indexUrl' => localized_route('tracks.index'),
        'csrfToken' => csrf_token(),
        'locale' => app()->getLocale(),
        'loadMessage' => __('ui.tracks.load_failed'),
        'renameMessage' => __('ui.tracks.rename_failed'),
        'deleteMessage' => __('ui.tracks.delete_failed'),
        'sessionMessage' => __('ui.maps.session_expired'),
        'rateLimitMessage' => __('ui.maps.rate_limit'),
        'usedOnceMessage' => __('ui.tracks.used_once'),
        'usedCountMessage' => __('ui.tracks.used_count'),
        'unusedMessage' => __('ui.tracks.unused'),
    ];
@endphp
<main id="track-library" x-data="trackLibrary(@js($trackLibraryConfig))" class="mx-auto grid w-full max-w-5xl gap-6 px-4 py-8 font-sans sm:px-6 sm:py-12">
    @include('maps.library-header', ['activeTab' => 'tracks'])
    <section aria-label="{{ __('ui.tracks.my_tracks') }}" class="min-w-0 rounded-xl border border-zinc-200 bg-white shadow-xs">
        <div role="search" class="border-b border-zinc-200 p-4 sm:px-5">
            <div class="relative sm:max-w-xs">
                <label for="track-search" class="sr-only">{{ __('ui.tracks.search') }}</label>
                <svg class="pointer-events-none absolute top-3 left-3 size-4 text-zinc-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5" /><path stroke-linecap="round" d="m16 16 4 4" /></svg>
                <input id="track-search" x-ref="search" x-model="query" @input="scheduleSearch()" name="q" type="search" maxlength="160" placeholder="{{ __('ui.tracks.search') }}" class="min-h-10 w-full rounded-lg border-zinc-200 bg-white py-2 pr-3 pl-9 text-base text-zinc-900 placeholder:text-zinc-400 focus:border-blue-500 focus:ring-blue-500 sm:text-sm" />
            </div>
        </div>
        <div x-cloak x-show="loading || error" class="border-b border-zinc-100 px-4 py-3 sm:px-5">
            <p x-show="loading" role="status" class="flex items-center gap-2 text-sm text-zinc-500"><span aria-hidden="true" class="size-4 animate-spin rounded-full border-2 border-zinc-200 border-t-blue-600"></span>{{ __('ui.tracks.loading') }}</p>
            <div x-show="error" role="alert" class="flex flex-wrap items-center gap-3 text-sm text-red-700"><p x-text="error"></p><button type="button" @click="load(retryAppend)" :disabled="loading" class="min-h-10 rounded-sm font-medium underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-blue-600 disabled:opacity-50">{{ __('ui.maps.retry') }}</button></div>
        </div>
        <div :aria-busy="loading" class="divide-y divide-zinc-100">
            <template x-for="item in items" :key="item.token">
                <article class="relative grid grid-cols-[minmax(0,1fr)_auto] items-start gap-x-2 gap-y-3 px-3 py-3 sm:grid-cols-[5rem_minmax(0,1fr)_auto] sm:gap-x-4 sm:px-5">
                    <a :href="item.url" :aria-label="item.name" data-rodnik-navigate data-rodnik-exact-url tabindex="-1" class="hidden overflow-hidden rounded-lg border border-blue-100 bg-blue-50/50 sm:block" data-track-preview>
                        @include('maps.preview', ['previewRecord' => 'item'])
                    </a>
                    <div class="col-span-full grid min-w-0 gap-0.5 self-center sm:col-span-1">
                        <div class="min-w-0 pr-10 sm:pr-0" data-track-title>
                            <a :href="item.url" x-text="item.name" data-rodnik-navigate data-rodnik-exact-url class="rounded-sm text-base leading-6 font-semibold wrap-anywhere text-zinc-900 hover:text-blue-700 focus-visible:outline-2 focus-visible:outline-blue-600"></a>
                        </div>
                        <p class="flex min-h-6 flex-wrap items-center gap-x-2 text-sm leading-5 text-zinc-500" data-track-metadata>
                            <span x-show="Number.isFinite(item.distance_km)" x-text="@js(__('ui.tracks.distance', ['distance' => ':distance'])).replace(':distance', distance(item.distance_km))"></span>
                            <span x-show="Number.isFinite(item.distance_km)" aria-hidden="true">·</span>
                            <span x-text="used(item)"></span>
                        </p>
                    </div>
                    <div class="absolute top-2 right-2 sm:relative sm:top-auto sm:right-auto" data-track-actions @click.outside="if (menuToken === item.token) menuToken = null" @keydown.escape.stop.prevent="menuToken = null; $el.querySelector('button').focus()">
                        <button type="button" @click="menuToken = menuToken === item.token ? null : item.token" :disabled="busy" :aria-expanded="menuToken === item.token" aria-haspopup="true" :aria-controls="'track-actions-' + item.token" aria-label="{{ __('ui.maps.more_actions') }}" class="inline-flex size-8 items-center justify-center rounded-lg text-zinc-500 hover:bg-zinc-100 focus-visible:outline-2 focus-visible:outline-blue-600 disabled:opacity-50 sm:size-9"><svg class="size-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="5" cy="12" r="1.5" /><circle cx="12" cy="12" r="1.5" /><circle cx="19" cy="12" r="1.5" /></svg></button>
                        <div x-cloak x-show="menuToken === item.token" :id="'track-actions-' + item.token" class="absolute top-full right-0 z-20 mt-1 grid w-44 gap-0.5 rounded-lg border border-zinc-200 bg-white p-1 shadow-lg">
                            <button type="button" @click="rename(item, $el.parentElement.previousElementSibling)" :disabled="busy" class="min-h-10 rounded-md px-3 text-left text-sm text-zinc-700 hover:bg-zinc-50 focus-visible:bg-zinc-50 focus-visible:outline-none">{{ __('ui.tracks.rename') }}</button>
                            <a :href="item.download_url" class="flex min-h-10 items-center rounded-md px-3 text-sm text-zinc-700 hover:bg-zinc-50 focus-visible:bg-zinc-50 focus-visible:outline-none">{{ __('ui.tracks.download') }}</a>
                            <div class="my-1 border-t border-zinc-100"></div>
                            <button type="button" @click="openDelete(item, $el.parentElement.previousElementSibling)" :disabled="busy" aria-haspopup="dialog" aria-controls="track-delete-dialog" class="min-h-10 rounded-md px-3 text-left text-sm text-red-700 hover:bg-red-50 focus-visible:bg-red-50 focus-visible:outline-none">{{ __('ui.tracks.delete') }}</button>
                        </div>
                    </div>
                    <template x-if="editingToken === item.token">
                        <form @submit.prevent="saveName(item)" @keydown.escape.stop.prevent="cancelRename()" :aria-busy="busy" class="col-span-full grid min-w-0 gap-3 rounded-lg border border-zinc-200 bg-zinc-50 p-3 sm:p-4">
                            <label :for="'track-name-' + item.token" class="text-sm font-medium text-zinc-700">{{ __('ui.tracks.name') }}</label>
                            <input data-track-rename x-model="draftName" :id="'track-name-' + item.token" :disabled="busy" type="text" maxlength="160" required :aria-invalid="Boolean(fieldError)" :aria-describedby="'track-name-error-' + item.token" class="min-h-10 w-full rounded-lg border-zinc-200 bg-white px-3 py-2 text-base text-zinc-900 focus:border-blue-500 focus:ring-blue-500 disabled:opacity-60 sm:text-sm" />
                            <p x-show="mutationError" :id="'track-name-error-' + item.token" x-text="mutationError" role="alert" class="text-sm text-red-700"></p>
                            <div class="flex flex-wrap gap-2"><button type="submit" :disabled="busy" class="map-button-primary">{{ __('ui.common.save_changes') }}</button><button type="button" @click="cancelRename()" :disabled="busy" class="map-button-secondary">{{ __('ui.common.cancel') }}</button></div>
                        </form>
                    </template>
                </article>
            </template>
            <div x-cloak x-show="loaded && !items.length && !loading && !error" class="flex flex-col items-center gap-3 px-6 py-16 text-center">
                <div class="mb-1 flex size-12 items-center justify-center rounded-xl border border-zinc-200 bg-zinc-50 text-zinc-500"><svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="6" cy="18" r="3" /><circle cx="18" cy="6" r="3" /><path stroke-linecap="round" d="M6 15V8a3 3 0 0 1 3-3h6M9 18h6a3 3 0 0 0 3-3v-2" /></svg></div>
                <div class="grid gap-1.5"><h2 x-text="query.trim() ? @js(__('ui.maps.no_search_results')) : @js(__('ui.tracks.empty'))" class="text-base font-semibold text-zinc-900"></h2><p x-text="query.trim() ? @js(__('ui.maps.no_search_results_help')) : @js(__('ui.tracks.empty_description'))" class="mx-auto max-w-sm text-sm leading-6 text-zinc-500"></p></div>
                <a x-show="!query.trim()" href="{{ localized_route('duo') }}" data-rodnik-navigate class="map-button-primary mt-2">{{ __('ui.maps.explore_map') }}</a>
                <button x-show="query.trim()" type="button" @click="query = ''; load(); $refs.search.focus()" class="map-button-secondary mt-2">{{ __('ui.maps.clear_search') }}</button>
            </div>
        </div>
        <div x-cloak x-show="nextPage" class="flex justify-center border-t border-zinc-100 px-4 py-4"><button type="button" @click="load(true)" :disabled="loading" class="map-button-secondary">{{ __('ui.tracks.more') }}</button></div>
    </section>
    <template x-teleport="body">
        <div x-cloak x-show="deletingRecord" x-trap.inert.noscroll="deletingRecord" @click.self="closeDelete()" @keydown.escape.stop.prevent="closeDelete()" class="fixed inset-0 z-90 flex items-center justify-center bg-zinc-950/40 p-4 font-sans">
            <section id="track-delete-dialog" role="dialog" aria-modal="true" aria-labelledby="track-delete-heading" aria-describedby="track-delete-help" :aria-busy="busy" class="grid max-h-[90dvh] w-full max-w-md gap-5 overflow-y-auto rounded-xl border border-zinc-200 bg-white p-5 shadow-xl sm:p-6">
                <div class="grid gap-2">
                    <h2 id="track-delete-heading" class="text-lg font-semibold tracking-tight text-zinc-900">{{ __('ui.tracks.delete_confirm') }}</h2>
                    <p x-text="deletingRecord?.name" class="wrap-anywhere text-sm font-medium text-zinc-700"></p>
                    <p id="track-delete-help" class="text-sm leading-6 text-zinc-500">{{ __('ui.tracks.delete_help') }}</p>
                </div>
                <div x-show="deletingRecord?.map_count" class="grid gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                    <p x-text="deletingRecord ? used(deletingRecord) : ''" class="font-semibold"></p>
                    <ul class="grid list-disc gap-1 pl-4"><template x-for="map in deletingRecord?.maps ?? []" :key="map.id"><li x-text="map.title" class="wrap-anywhere"></li></template></ul>
                    <p x-show="deletingRecord?.other_maps_count" x-text="@js(__('ui.tracks.other_maps', ['count' => ':count'])).replace(':count', deletingRecord?.other_maps_count ?? 0)"></p>
                </div>
                <p x-show="mutationError" x-text="mutationError" role="alert" class="text-sm text-red-700"></p>
                <div class="flex justify-end gap-2 text-sm">
                    <button type="button" x-ref="cancelDelete" @click="closeDelete()" :disabled="busy" class="map-button-secondary">{{ __('ui.common.cancel') }}</button>
                    <button type="button" @click="remove()" :disabled="busy" class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg bg-red-700 px-4 py-2 font-medium text-white hover:bg-red-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700 disabled:opacity-50"><span x-cloak x-show="busy" aria-hidden="true" class="size-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>{{ __('ui.tracks.delete') }}</button>
                </div>
            </section>
        </div>
    </template>
</main>
