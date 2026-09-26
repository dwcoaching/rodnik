<template x-teleport="body">
    <div x-cloak x-show="inlineEditing" x-trap.inert.noscroll="inlineEditing" @click.self="cancelInline()" @keydown.escape.stop.prevent="cancelInline()" class="fixed inset-0 z-90 flex items-center justify-center bg-zinc-950/40 p-4 font-sans">
        <section id="map-editor-dialog" role="dialog" aria-modal="true" aria-labelledby="map-editor-heading" :aria-busy="busy" class="grid max-h-[90dvh] w-full max-w-lg gap-5 overflow-y-auto rounded-xl border border-zinc-200 bg-white p-5 shadow-xl sm:p-6">
            <div class="flex items-center justify-between gap-4">
                <h2 id="map-editor-heading" class="text-xl font-bold tracking-tight text-zinc-900">{{ __('ui.maps.edit_map') }}</h2>
                <button type="button" @click="cancelInline()" :disabled="busy" aria-label="{{ __('ui.common.close') }}" class="inline-flex size-8 items-center justify-center rounded-md text-zinc-400 hover:bg-zinc-100 hover:text-zinc-700 focus-visible:outline-2 focus-visible:outline-blue-600 disabled:opacity-50"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" d="m6 6 12 12M6 18 18 6" /></svg></button>
            </div>
            <form novalidate x-ref="editor" @submit.prevent="saveInline(true)" @keydown.enter="if ($event.isComposing) $event.preventDefault()" class="grid gap-5">
                @include('maps.details-fields', ['fieldPrefix' => 'map-editor'])
                <p x-cloak x-show="error && !Object.keys(fieldErrors).length" x-text="error" role="alert" class="text-sm text-[#dc3545]"></p>
                <div class="flex justify-end gap-2 border-t border-zinc-100 pt-4">
                    <button type="button" @click="cancelInline()" :disabled="busy" class="map-button-secondary">{{ __('ui.common.cancel') }}</button>
                    <button type="submit" :disabled="busy || slugStatus !== 'valid'" class="map-button-primary"><span x-cloak x-show="busy" aria-hidden="true" class="size-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>{{ __('ui.common.save_changes') }}</button>
                </div>
            </form>
        </section>
    </div>
</template>
