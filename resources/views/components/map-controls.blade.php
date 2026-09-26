<div x-data="maps({
        endpoint: @js(route('maps.store')),
        slugEndpoint: @js(localized_route('maps.check-slug')),
        slugCheckingMessage: @js(__('ui.maps.link_checking')),
        slugAvailableMessage: @js(__('ui.maps.link_available')),
        slugInvalidMessage: @js(__('ui.maps.link_invalid')),
        slugUnavailableMessage: @js(__('ui.maps.link_unavailable')),
        slugCheckFailedMessage: @js(__('ui.maps.link_check_failed')),
        titleRequiredMessage: @js(__('ui.maps.title_required')),
        defaultTitleDate: @js(__('ui.maps.default_title_date')),
        baseUrl: @js(url(localized_public_path())),
        ownerId: @js(auth()->id()),
        csrfToken: @js(csrf_token()),
        locale: @js(app()->getLocale()),
        copiedMessage: @js(__('ui.maps.link_copied')),
        errorMessage: @js(__('ui.maps.save_failed')),
        trackErrorMessage: @js(__('ui.maps.track_upload_failed')),
        sessionMessage: @js(__('ui.maps.session_expired')),
        rateLimitMessage: @js(__('ui.maps.rate_limit')),
        copyMessage: @js(__('ui.maps.copy_manually')),
        updateErrorMessage: @js(__('ui.maps.update_failed')),
        conflictMessage: @js(__('ui.maps.conflict')),
        updatedMessage: @js(__('ui.maps.saved')),
        savedMessage: @js(__('ui.maps.added_to_library')),
    })"
    class="pointer-events-auto absolute top-0 right-full mr-2 flex w-max max-w-[calc(100vw-6rem)] items-center gap-2 font-sans text-zinc-900"
    @keydown.escape.window="if (opened && !$event.defaultPrevented) close()"
>
    <button type="button" x-ref="trigger" @click="open()" :disabled="!ready || busy"
        aria-haspopup="dialog" aria-controls="share-map-dialog" :aria-expanded="opened"
        class="inline-flex h-9 cursor-pointer items-center justify-center gap-2 rounded-md bg-white px-3 text-sm font-bold text-black shadow-xs hover:text-blue-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 disabled:opacity-50">
        <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71m2.25 5.82a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71" /></svg>
        <span>{{ __('ui.maps.share') }}</span>
        <span x-cloak x-show="dirty" class="size-2 shrink-0 rounded-full bg-blue-600" aria-hidden="true"></span>
        <span x-cloak x-show="dirty" class="sr-only">{{ __('ui.maps.unsaved') }}</span>
    </button>

    <template x-teleport="body">
        <div x-cloak x-show="opened" x-trap.inert.noscroll="opened" @click.self="close()"
            class="fixed inset-0 z-[10020] flex items-end justify-center bg-zinc-950/40 p-3 font-sans sm:items-center sm:p-5">
            <section id="share-map-dialog" role="dialog" aria-modal="true" aria-labelledby="share-map-heading" :aria-describedby="confirmation ? 'share-map-confirmation' : saving ? null : 'share-map-description'" :aria-busy="busy"
                class="max-h-[90dvh] w-full max-w-lg overflow-y-auto rounded-xl border border-zinc-200 bg-white p-5 text-zinc-900 shadow-xl sm:p-6">
                <div class="flex items-start justify-between gap-4">
                    <h2 id="share-map-heading" class="text-xl font-bold tracking-tight" x-text="confirmation === 'created' ? @js(__('ui.maps.confirmation_saved_title')) : confirmation === 'updated' ? @js(__('ui.maps.confirmation_updated_title')) : saving ? @js(__('ui.maps.create_map')) : @js(__('ui.maps.share_title'))">{{ __('ui.maps.share_title') }}</h2>
                    <button type="button" @click="close()" class="-mr-2 -mt-2 inline-flex size-10 shrink-0 items-center justify-center rounded-md text-zinc-500 transition-colors hover:bg-zinc-100 hover:text-zinc-900 focus-visible:outline-2 focus-visible:outline-blue-600" aria-label="{{ __('ui.common.close') }}">
                        <svg class="size-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m5 5 10 10M5 15 15 5" /></svg>
                    </button>
                </div>
                <p id="share-map-description" x-show="!saving && !confirmation" class="mt-2 text-sm leading-6 text-zinc-500">{{ __('ui.maps.share_view_help') }}</p>
                <div x-show="!saving && !confirmation" class="mt-5" data-share-current-view>
                    <div class="flex items-center gap-1 rounded-lg border border-zinc-200 bg-zinc-50 p-1 focus-within:border-blue-600 focus-within:ring-1 focus-within:ring-blue-600">
                        <input id="shared-map-link" x-ref="link" type="text" :value="linkUrl" :placeholder="trackFailed() ? @js(__('ui.maps.track_upload_failed')) : @js(__('ui.maps.uploading_track'))" readonly dir="ltr" @focus="$el.setSelectionRange(0, 0); $el.scrollLeft = 0" @click="$el.setSelectionRange(0, $el.value.length, 'backward'); $el.scrollLeft = 0" aria-label="{{ __('ui.maps.link') }}"
                            class="block min-w-0 flex-1 border-0 bg-transparent py-2 pl-2 pr-1 text-base text-zinc-700 focus:ring-0 sm:text-sm" />
                        <button type="button" @click="copy()" :disabled="busy || !linkUrl" :aria-label="copied ? @js(__('ui.maps.copied')) : @js(__('ui.maps.copy_link'))"
                            class="inline-flex min-h-10 shrink-0 items-center gap-1.5 rounded-md border border-blue-600 bg-blue-600 px-3 text-sm font-bold text-white shadow-xs transition-colors hover:bg-blue-700 focus-visible:bg-blue-700 focus-visible:outline-none disabled:opacity-40">
                            <svg x-show="!copied" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="8" y="8" width="12" height="12" rx="2" /><path stroke-linecap="round" d="M16 8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h3" /></svg>
                            <svg x-cloak x-show="copied" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6" /></svg>
                            <span x-text="copied ? @js(__('ui.maps.copied')) : @js(__('ui.maps.copy'))">{{ __('ui.maps.copy') }}</span>
                        </button>
                    </div>
                    <p x-cloak x-show="trackPending() && !trackFailed()" role="status" class="mt-3 flex items-center gap-2 text-sm text-zinc-500"><span aria-hidden="true" class="size-4 animate-spin rounded-full border-2 border-zinc-200 border-t-blue-600"></span>{{ __('ui.maps.uploading_track') }}</p>
                    <div x-cloak x-show="trackFailed()" class="mt-3 flex items-center justify-between gap-3 text-sm">
                        <p class="text-[#dc3545]">{{ __('ui.maps.track_upload_failed') }}</p>
                        <button type="button" @click="retry()" class="font-bold text-blue-600 hover:underline">{{ __('ui.maps.retry') }}</button>
                    </div>
                    <p x-cloak x-show="trackMissing()" class="mt-3 rounded-md bg-amber-50 p-3 text-sm leading-5 text-amber-900">{{ __('ui.maps.deleted_track_notice') }}</p>
                </div>

                <div x-cloak x-show="!saving && savedUrl()" class="mt-5" :class="confirmation ? '' : 'border-t border-zinc-100 pt-5'" data-share-saved-view>
                    <label x-show="!confirmation" for="saved-map-link" class="mb-2 block text-sm font-bold">{{ __('ui.maps.saved_map_link') }} <span class="ml-1 font-normal text-zinc-500" x-text="sharedMap()?.title"></span></label>
                    <div class="flex items-center gap-1 rounded-lg border border-zinc-200 p-1 focus-within:border-blue-600 focus-within:ring-1 focus-within:ring-blue-600">
                        <input id="saved-map-link" x-ref="savedLink" type="text" :value="savedUrl()" readonly dir="ltr" aria-label="{{ __('ui.maps.link') }}" @focus="$el.setSelectionRange(0, 0); $el.scrollLeft = 0" @click="$el.setSelectionRange(0, $el.value.length, 'backward'); $el.scrollLeft = 0" class="block min-w-0 flex-1 border-0 bg-transparent py-2 pl-2 pr-1 text-base text-zinc-600 focus:ring-0 sm:text-sm" />
                        <button type="button" @click="copy(true)" :disabled="busy" class="min-h-10 shrink-0 px-3 focus-visible:outline-none" :class="confirmation ? 'map-button-primary focus-visible:bg-blue-700' : 'map-button-secondary focus-visible:bg-zinc-100'" :aria-label="copiedSaved ? @js(__('ui.maps.copied')) : @js(__('ui.maps.copy_saved_link'))"><span x-text="copiedSaved ? @js(__('ui.maps.copied')) : @js(__('ui.maps.copy'))">{{ __('ui.maps.copy') }}</span></button>
                    </div>
                    <p x-cloak x-show="!confirmation && canUpdate() && dirty" class="mt-2 text-sm leading-6 text-zinc-700">{{ __('ui.maps.saved_version') }}</p>
                </div>

                <p id="share-map-confirmation" x-cloak x-show="confirmation" class="mt-3 text-sm leading-6 text-zinc-500">
                    @foreach (['created' => 'confirmation_saved', 'updated' => 'confirmation_updated'] as $result => $message)
                        @php([$beforeLink, $afterLink] = explode(':maps', __('ui.maps.'.$message), 2))
                        <span x-show="confirmation === '{{ $result }}'">{{ $beforeLink }}<a href="{{ localized_route('maps.index') }}" data-rodnik-navigate class="rounded-sm font-semibold text-blue-600 underline underline-offset-2 hover:text-blue-700 focus-visible:outline-2 focus-visible:outline-blue-600">{{ __('ui.maps.my_maps') }}</a>{{ $afterLink }}</span>
                    @endforeach
                </p>

                <div x-show="!saving && !confirmation && !savedUrl()" class="mt-5 border-t border-zinc-200" aria-hidden="true" data-share-divider></div>
                @auth
                    <div x-cloak x-show="!saving && !confirmation" class="mt-4 grid gap-3" data-share-save-actions>
                        <p x-show="!canUpdate()" class="text-left text-sm leading-5 text-zinc-500">{{ __('ui.maps.keep_view_help') }}</p>
                        <div class="grid gap-2" :class="canUpdate() ? 'grid-cols-2' : 'grid-cols-1'">
                            <button type="button" x-show="canUpdate()" @click="update()" :disabled="busy || !dirty || trackPending()" class="map-button-primary min-h-10 w-full min-w-0">{{ __('ui.maps.update_existing_map') }}</button>
                            <button type="button" @click="beginSave()" :disabled="busy || trackPending()" class="map-button-secondary min-h-10 w-full min-w-0"><span x-text="canUpdate() ? @js(__('ui.maps.create_copy')) : @js(__('ui.maps.create_map'))">{{ __('ui.maps.create_map') }}</span></button>
                        </div>
                    </div>
                    <form novalidate x-cloak x-show="saving" @submit.prevent="save()" @keydown.enter="if ($event.isComposing) $event.preventDefault()" class="mt-5 space-y-5">
                        @include('maps.details-fields', ['fieldPrefix' => 'save-map'])
                        <div class="flex justify-end gap-2 border-t border-zinc-100 pt-4">
                            <button type="button" @click="cancelSave()" :disabled="busy" class="map-button-secondary min-h-10">{{ __('ui.maps.cancel') }}</button>
                            <button type="submit" :disabled="busy || trackPending() || slugStatus !== 'valid'" class="map-button-primary min-h-10"><span x-cloak x-show="busy" aria-hidden="true" class="size-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>{{ __('ui.maps.create_map') }}</button>
                        </div>
                    </form>
                @else
                    <p class="mt-4 text-sm leading-6 text-zinc-500"><a href="{{ localized_route('login') }}" data-rodnik-navigate class="font-semibold text-blue-600 hover:underline">{{ __('ui.maps.sign_in_to_save') }}</a> {{ __('ui.maps.guest_track_help') }}</p>
                @endauth
                <p x-show="notice && (!confirmation || copied || copiedSaved || notice === @js(__('ui.maps.copy_manually')))" x-text="notice" role="status" aria-live="polite" :class="copied || copiedSaved ? 'sr-only' : 'mt-4 rounded-lg bg-blue-50 px-3 py-2.5 text-sm leading-5 text-blue-800'"></p>
                <p x-show="error && !Object.keys(fieldErrors).length" x-text="error" role="alert" class="mt-4 text-sm text-[#dc3545]"></p>
            </section>
        </div>
    </template>
</div>
