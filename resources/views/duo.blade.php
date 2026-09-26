<x-duo-layout :shared-map="$sharedMap ?? null" :missing-map="$missingMap ?? false">
    <livewire:duo :page="$page ?? []" :shared-state="$sharedMap['state'] ?? null" />
    @if ($missingMap ?? false)
        <div x-data="{ opened: true }" @keydown.escape.window="if (opened) opened = false">
            <template x-teleport="body">
                <div x-cloak x-show="opened" x-trap.inert.noscroll="opened" @click.self="opened = false"
                    class="fixed inset-0 z-[10020] flex items-end justify-center bg-black/30 p-3 font-interface sm:items-center sm:p-5">
                    <section id="missing-map-dialog" role="dialog" aria-modal="true" aria-labelledby="missing-map-heading" aria-describedby="missing-map-description"
                        class="w-full max-w-md rounded-xl border border-zinc-200 bg-white p-5 text-zinc-900 shadow-xl sm:p-6">
                        <h2 id="missing-map-heading" class="text-lg font-semibold tracking-tight">{{ __('ui.maps.not_found') }}</h2>
                        <p id="missing-map-description" class="mt-2 text-sm leading-6 text-zinc-500">{{ __('ui.maps.not_found_help') }}</p>
                        <div class="mt-5 flex justify-end">
                            <button type="button" @click="opened = false" class="map-button-primary">{{ __('ui.common.close') }}</button>
                        </div>
                    </section>
                </div>
            </template>
        </div>
    @endif
</x-duo-layout>
