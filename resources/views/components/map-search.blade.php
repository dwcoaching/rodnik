@php($kbd = 'inline-flex h-5 min-w-5 items-center justify-center rounded-sm border border-zinc-200 bg-white px-1 font-sans text-[11px] font-semibold leading-none text-zinc-500 shadow-[0_1px_0_rgb(0_0_0/0.04)]')
<div x-data="mapSearch" class="contents">
    <div x-cloak class="absolute left-10 top-2 sm:block [#map:has(.ol-touch)_&]:left-[51px]" style="z-index: 10000;" :class="{ 'hidden': minimized }">
        <button type="button" x-ref="button" @click="open($el)"
            aria-haspopup="dialog" aria-controls="map-search-dialog" :aria-expanded="isOpen ? 'true' : 'false'"
            aria-label="{{ __('ui.map.search.button') }}" :aria-label="t.button"
            title="{{ __('ui.map.search.button') }}" :title="buttonTitle"
            class="flex h-9 w-9 cursor-pointer items-center justify-center rounded-md border-2 bg-white shadow-xs focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600"
            :class="isOpen ? 'border-blue-600 text-blue-700' : 'border-white text-black hover:text-blue-700'">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="h-5 w-5" aria-hidden="true">
                <path fill-rule="evenodd" d="M9.965 11.026a5 5 0 1 1 1.06-1.06l2.755 2.754a.75.75 0 1 1-1.06 1.06l-2.755-2.754ZM10.5 7a3.5 3.5 0 1 1-7 0 3.5 3.5 0 0 1 7 0Z" clip-rule="evenodd" />
            </svg>
        </button>
    </div>

    {{-- OpenLayers listens for keys on #map: arrows and +/- on a focused button would move the map behind the dialog. --}}
    <dialog id="map-search-dialog" x-ref="dialog"
        aria-label="{{ __('ui.map.search.label') }}" :aria-label="t.label"
        @close="onClose()" @pointerdown="pointerDown($event)" @click="backdropClick($event)" @keydown.stop @keypress.stop
        class="map-search-dialog fixed inset-0 m-0 h-full max-h-none w-full max-w-none overflow-y-auto overscroll-contain border-0 bg-transparent p-0 font-sans text-zinc-900">
        <div class="flex min-h-full items-start justify-center px-2 pb-6 pt-2 sm:px-4 sm:pt-[12vh]">
            <div x-ref="panel" class="map-search-panel flex w-full flex-col overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-2xl sm:max-w-xl">
                <div class="flex h-14 shrink-0 items-center gap-1 border-b border-zinc-100 pl-4 pr-2">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-5 shrink-0 text-zinc-400" aria-hidden="true">
                        <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11ZM2 9a7 7 0 1 1 12.452 4.391l3.328 3.329a.75.75 0 1 1-1.06 1.06l-3.329-3.328A7 7 0 0 1 2 9Z" clip-rule="evenodd" />
                    </svg>
                    <input x-ref="input" type="text" x-model="query" @input="update()" @keydown="keydown($event)" autofocus
                        role="combobox" aria-autocomplete="list" aria-controls="map-search-listbox"
                        :aria-expanded="items.length > 0 ? 'true' : 'false'" :aria-activedescendant="activeId"
                        aria-label="{{ __('ui.map.search.label') }}" :aria-label="t.label"
                        placeholder="{{ __('ui.map.search.placeholder') }}" :placeholder="placeholder"
                        autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" enterkeyhint="go"
                        class="h-full min-w-0 flex-1 border-0 bg-transparent px-2 py-0 text-base text-zinc-900 placeholder:text-zinc-400 focus:outline-none focus:ring-0">
                    <span x-show="spinner" class="mx-1 size-4 shrink-0 animate-spin rounded-full border-2 border-zinc-200 border-t-blue-600" aria-hidden="true"></span>
                    <button type="button" x-show="query !== ''" @click="clear()" :aria-label="t.clear" :title="t.clear"
                        class="inline-flex size-8 shrink-0 cursor-pointer items-center justify-center rounded-md text-zinc-400 transition-colors hover:bg-zinc-100 hover:text-zinc-700 focus-visible:outline-2 focus-visible:outline-blue-600">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="size-4" aria-hidden="true">
                            <path d="M5.28 4.22a.75.75 0 0 0-1.06 1.06L6.94 8l-2.72 2.72a.75.75 0 1 0 1.06 1.06L8 9.06l2.72 2.72a.75.75 0 1 0 1.06-1.06L9.06 8l2.72-2.72a.75.75 0 0 0-1.06-1.06L8 6.94 5.28 4.22Z" />
                        </svg>
                    </button>
                    <button type="button" @click="close()" :aria-label="t.close" :title="t.close"
                        class="ml-1 hidden h-6 shrink-0 cursor-pointer items-center rounded-md border border-zinc-200 bg-zinc-50 px-1.5 text-[11px] font-semibold text-zinc-500 transition-colors hover:bg-zinc-100 hover:text-zinc-700 focus-visible:outline-2 focus-visible:outline-blue-600 pointer-fine:inline-flex">esc</button>
                    <button type="button" @click="close()" x-text="t.cancel"
                        class="shrink-0 cursor-pointer rounded-md px-2 py-2 text-sm font-semibold text-blue-600 focus-visible:outline-2 focus-visible:outline-blue-600 pointer-fine:hidden">{{ __('ui.map.search.cancel') }}</button>
                </div>

                <div class="max-h-[min(60vh,28rem)] overflow-y-auto overscroll-contain">
                    <div x-show="view === 'recent'" class="flex items-center justify-between px-4 pb-1 pt-3">
                        <span id="map-search-recent" class="text-xs font-semibold text-zinc-500" x-text="t.recent">{{ __('ui.map.search.recent') }}</span>
                        <button type="button" @click="clearRecents()" x-text="t.clear_recent"
                            class="cursor-pointer rounded-sm text-xs font-semibold text-blue-600 hover:text-blue-700 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600">{{ __('ui.map.search.clear_recent') }}</button>
                    </div>
                    <p x-show="view === 'list' && corrected" class="px-4 pt-3 text-xs text-zinc-500">
                        <span x-text="parts(t.corrected).before"></span><span class="font-semibold text-zinc-800" x-text="corrected"></span><span x-text="parts(t.corrected).after"></span>
                    </p>

                    {{-- Grouped as in the WAI-ARIA listbox pattern: arrow keys still move through every row as one list. --}}
                    <div id="map-search-listbox" role="listbox" x-show="view === 'recent' || view === 'list'"
                        :aria-labelledby="view === 'recent' ? 'map-search-recent' : null" :aria-label="view === 'recent' ? null : t.label"
                        class="p-2">
                        <template x-for="section in sections" :key="section.key">
                            <ul :role="section.label ? 'group' : 'none'" :aria-labelledby="section.label ? section.headerId : null">
                                <template x-if="section.label">
                                    <li :id="section.headerId" role="presentation" x-text="section.label"
                                        class="select-none px-2 pb-1 text-xs font-semibold text-zinc-500 sm:px-3" :class="section.position === 0 ? 'pt-1' : 'pt-3'"></li>
                                </template>
                                <template x-for="entry in section.entries" :key="itemKey(entry.item)">
                                    <li :id="optionId(entry.index)" role="option" :aria-selected="entry.index === activeIndex ? 'true' : 'false'"
                                        @mousemove="hover(entry.index, $event)" @mousedown.prevent @click="choose(entry.item, $event)"
                                        class="group flex cursor-pointer select-none items-center gap-3 rounded-lg px-2 py-2 sm:px-3"
                                        :class="entry.index === activeIndex ? 'bg-blue-50' : ''">
                                        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg border transition-colors" aria-hidden="true"
                                            :class="entry.index === activeIndex ? 'border-blue-200 bg-white text-blue-600' : 'border-zinc-200/80 bg-zinc-50 text-zinc-500'">
                                            <svg x-show="icon(entry.item) === 'country'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-5"><path fill-rule="evenodd" d="M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0Zm-1.503.204A6.5 6.5 0 1 1 7.95 3.83L6.927 5.62a1.453 1.453 0 0 0 1.91 2.02l.175-.087a.5.5 0 0 1 .224-.053h.146a.5.5 0 0 1 .447.724l-.028.055a.4.4 0 0 1-.357.221h-.502a2.26 2.26 0 0 0-1.88 1.006l-.044.066a2.099 2.099 0 0 0 1.085 3.156.58.58 0 0 1 .397.547v1.05a1.175 1.175 0 0 0 2.093.734l1.611-2.014c.192-.24.296-.536.296-.842 0-.316.128-.624.353-.85a1.363 1.363 0 0 0 .173-1.716l-.464-.696a.369.369 0 0 1 .527-.499l.343.257c.316.237.738.275 1.091.098a.586.586 0 0 1 .677.11l1.297 1.297Z" clip-rule="evenodd" /></svg>
                                            <svg x-show="icon(entry.item) === 'region'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-5"><path d="M3.5 2.75a.75.75 0 0 0-1.5 0v14.5a.75.75 0 0 0 1.5 0v-4.392l1.657-.348a6.449 6.449 0 0 1 4.271.572 7.948 7.948 0 0 0 5.965.524l2.078-.64A.75.75 0 0 0 18 12.25v-8.5a.75.75 0 0 0-.904-.734l-2.38.501a7.25 7.25 0 0 1-4.186-.363l-.502-.2a8.75 8.75 0 0 0-5.053-.439l-1.475.31V2.75Z" /></svg>
                                            <svg x-show="icon(entry.item) === 'map'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-5"><path fill-rule="evenodd" d="M8.157 2.176a1.5 1.5 0 0 0-1.147 0l-4.084 1.69A1.5 1.5 0 0 0 2 5.25v10.877a1.5 1.5 0 0 0 2.074 1.386l3.51-1.452 4.26 1.762a1.5 1.5 0 0 0 1.146 0l4.083-1.69A1.5 1.5 0 0 0 18 14.75V3.872a1.5 1.5 0 0 0-2.073-1.386l-3.51 1.452-4.26-1.762ZM7.58 5a.75.75 0 0 1 .75.75v6.5a.75.75 0 0 1-1.5 0v-6.5A.75.75 0 0 1 7.58 5Zm5.59 2.75a.75.75 0 0 0-1.5 0v6.5a.75.75 0 0 0 1.5 0v-6.5Z" clip-rule="evenodd" /></svg>
                                            <svg x-show="icon(entry.item) === 'city'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-5"><path fill-rule="evenodd" d="M1 2.75A.75.75 0 0 1 1.75 2h10.5a.75.75 0 0 1 0 1.5H12v13.75a.75.75 0 0 1-.75.75h-1.5a.75.75 0 0 1-.75-.75v-2.5a.75.75 0 0 0-.75-.75h-2.5a.75.75 0 0 0-.75.75v2.5a.75.75 0 0 1-.75.75h-2.5a.75.75 0 0 1 0-1.5H2v-13h-.25A.75.75 0 0 1 1 2.75ZM4 5.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5v-1ZM4.5 9a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5h-1ZM8 5.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5v-1ZM8.5 9a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5h-1ZM14.25 6a.75.75 0 0 0-.75.75V17a1 1 0 0 0 1 1h3.75a.75.75 0 0 0 0-1.5H18v-9h.25a.75.75 0 0 0 0-1.5h-4Zm.5 3.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5v-1Zm.5 3.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5h-1Z" clip-rule="evenodd" /></svg>
                                            <svg x-show="icon(entry.item) === 'spring'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-5"><path d="M10 2.25a.9.9 0 0 0-.72.36C8.06 4.25 4.5 9.06 4.5 12.3a5.5 5.5 0 0 0 11 0c0-3.24-3.56-8.05-4.78-9.69A.9.9 0 0 0 10 2.25Z" /></svg>
                                            <svg x-show="icon(entry.item) === 'coordinates'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-5"><path d="M4.25 2A2.25 2.25 0 0 0 2 4.25v2a.75.75 0 0 0 1.5 0v-2a.75.75 0 0 1 .75-.75h2a.75.75 0 0 0 0-1.5h-2ZM13.75 2a.75.75 0 0 0 0 1.5h2a.75.75 0 0 1 .75.75v2a.75.75 0 0 0 1.5 0v-2A2.25 2.25 0 0 0 15.75 2h-2ZM3.5 13.75a.75.75 0 0 0-1.5 0v2A2.25 2.25 0 0 0 4.25 18h2a.75.75 0 0 0 0-1.5h-2a.75.75 0 0 1-.75-.75v-2ZM18 13.75a.75.75 0 0 0-1.5 0v2a.75.75 0 0 1-.75.75h-2a.75.75 0 0 0 0 1.5h2A2.25 2.25 0 0 0 18 15.75v-2ZM7 10a3 3 0 1 1 6 0 3 3 0 0 1-6 0Z" /></svg>
                                            <svg x-show="icon(entry.item) === 'recent'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-5"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm.75-13a.75.75 0 0 0-1.5 0v5c0 .414.336.75.75.75h4a.75.75 0 0 0 0-1.5h-3.25V5Z" clip-rule="evenodd" /></svg>
                                        </span>
                                        <span class="min-w-0 flex-1">
                                            <span class="block truncate text-sm font-semibold text-zinc-900"><template x-for="(segment, segmentIndex) in segments(entry.item.name)" :key="segmentIndex"><span :class="segment.match ? 'text-blue-700' : ''" x-text="segment.text"></span></template><template x-if="entry.item.alt_name"><span class="font-normal text-zinc-500"><span aria-hidden="true"> – </span><template x-for="(segment, segmentIndex) in segments(entry.item.alt_name)" :key="segmentIndex"><span :class="segment.match ? 'font-semibold text-zinc-700' : ''" x-text="segment.text"></span></template></span></template></span>
                                            <span class="block truncate text-xs text-zinc-500" x-text="subtitle(entry.item)"></span>
                                            <span class="sr-only" x-text="t.types[entry.item.type]"></span>
                                        </span>
                                        <a x-show="hasPage(entry.item)" :href="entry.item.url" data-rodnik-navigate data-rodnik-exact-url tabindex="-1"
                                            :title="t.open_page" :aria-label="t.open_page" @click="openPage(entry.item, $event)"
                                            class="flex size-8 shrink-0 items-center justify-center rounded-md text-zinc-400 transition hover:bg-white hover:text-blue-700 hover:shadow-xs pointer-coarse:opacity-100"
                                            :class="entry.index === activeIndex ? 'opacity-100' : 'opacity-0 group-hover:opacity-100'">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="size-4" aria-hidden="true">
                                                <path d="M6.22 8.72a.75.75 0 0 0 1.06 1.06l5.22-5.22v1.69a.75.75 0 0 0 1.5 0v-3.5a.75.75 0 0 0-.75-.75h-3.5a.75.75 0 0 0 0 1.5h1.69L6.22 8.72Z" />
                                                <path d="M3.5 6.75c0-.69.56-1.25 1.25-1.25H7A.75.75 0 0 0 7 4H4.75A2.75 2.75 0 0 0 2 6.75v4.5A2.75 2.75 0 0 0 4.75 14h4.5A2.75 2.75 0 0 0 12 11.25V9a.75.75 0 0 0-1.5 0v2.25c0 .69-.56 1.25-1.25 1.25h-4.5c-.69 0-1.25-.56-1.25-1.25v-4.5Z" />
                                            </svg>
                                        </a>
                                        <span x-show="entry.index === activeIndex" class="hidden w-4 shrink-0 text-center text-sm text-blue-600/70 pointer-fine:inline" aria-hidden="true">↵</span>
                                    </li>
                                </template>
                            </ul>
                        </template>
                    </div>

                    <p x-show="view === 'hint'" class="px-6 py-8 text-center text-sm leading-6 text-zinc-500" x-text="t.empty_hint">{{ __('ui.map.search.empty_hint') }}</p>
                    <div x-show="view === 'empty'" class="px-6 py-8 text-center">
                        <p class="break-words text-sm font-semibold text-zinc-900"><span x-text="parts(t.no_results).before"></span><span x-text="resultsFor"></span><span x-text="parts(t.no_results).after"></span></p>
                        <p class="mt-1 text-sm text-zinc-500" x-text="t.no_results_hint"></p>
                    </div>
                    <div x-show="view === 'error'" class="flex flex-col items-center gap-3 px-6 py-8 text-center">
                        <p class="text-sm text-zinc-700" x-text="error === 'rate_limited' ? t.rate_limited : t.error"></p>
                        <button type="button" @click="retry(); focusInput()" class="map-button-secondary min-h-9 cursor-pointer py-1.5" x-text="t.retry"></button>
                    </div>
                </div>

                <div class="hidden shrink-0 items-center justify-between gap-4 border-t border-zinc-100 bg-zinc-50/70 px-4 py-2 text-xs text-zinc-500 sm:pointer-fine:flex" aria-hidden="true">
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                        <span class="inline-flex items-center gap-1"><kbd class="{{ $kbd }}">↑</kbd><kbd class="{{ $kbd }}">↓</kbd><span x-text="t.keys.navigate"></span></span>
                        <span class="inline-flex items-center gap-1"><kbd class="{{ $kbd }}">↵</kbd><span x-text="t.keys.select"></span></span>
                        <span class="inline-flex items-center gap-1"><kbd class="{{ $kbd }}" x-text="isMac ? '⌘↵' : 'Ctrl ↵'"></kbd><span x-text="t.keys.open_page"></span></span>
                        <span class="inline-flex items-center gap-1"><kbd class="{{ $kbd }}">esc</kbd><span x-text="t.keys.close"></span></span>
                    </div>
                    <span class="inline-flex shrink-0 items-center gap-1"><kbd class="{{ $kbd }}">/</kbd><span x-text="t.or"></span><kbd class="{{ $kbd }}" x-text="shortcutLabel"></kbd></span>
                </div>
                <p class="sr-only" role="status" aria-live="polite" x-text="announcement"></p>
            </div>
        </div>
    </dialog>
</div>
