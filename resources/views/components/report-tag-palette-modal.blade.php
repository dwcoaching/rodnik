@php
    $paletteTypes = [
        'success' => [
            'title' => __('ui.home.tag_palette.success'),
            'description' => __('ui.home.tag_palette.success_description'),
            'example' => __('ui.report.conditions.running'),
        ],
        'warning' => [
            'title' => __('ui.home.tag_palette.warning'),
            'description' => __('ui.home.tag_palette.warning_description'),
            'example' => __('ui.report.conditions.dripping'),
        ],
        'danger' => [
            'title' => __('ui.home.tag_palette.danger'),
            'description' => __('ui.home.tag_palette.danger_description'),
            'example' => __('ui.report.conditions.dry'),
        ],
    ];

    $colorRoles = [
        'border' => __('ui.home.tag_palette.border'),
        'background' => __('ui.home.tag_palette.background'),
        'text' => __('ui.home.tag_palette.text'),
    ];
@endphp

<div
    x-data="reportTagPalettePicker()"
    x-on:open-report-tag-palette.window="open()"
    x-show="isOpen"
    x-cloak
    x-on:click.self="cancel()"
    x-on:keydown.escape.window="if (isOpen) cancel()"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
    role="dialog"
    aria-modal="true"
    aria-labelledby="report-tag-palette-title"
    x-transition:enter="ease-out duration-300"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="ease-in duration-200"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
>
    <div
        x-trap.noscroll.inert="isOpen"
        x-on:click.stop
        class="flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-lg bg-white shadow-xl"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
    >
        <div class="flex shrink-0 items-start justify-between gap-4 border-b border-gray-200 p-5 sm:p-6">
            <div>
                <h2 id="report-tag-palette-title" class="text-lg font-semibold text-gray-900">
                    {{ __('ui.home.tag_palette.title') }}
                </h2>
                <p class="mt-1 text-sm text-gray-600">{{ __('ui.home.tag_palette.description') }}</p>
            </div>
            <button
                x-ref="closeButton"
                type="button"
                x-on:click="cancel()"
                class="shrink-0 rounded-md p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 focus:outline-hidden focus:ring-2 focus:ring-blue-600"
                aria-label="{{ __('ui.common.close') }}"
            >
                <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <div class="space-y-4 overflow-y-auto p-5 sm:p-6">
            @foreach ($paletteTypes as $type => $settings)
                <section class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 class="font-semibold text-gray-900">{{ $settings['title'] }}</h3>
                            <p class="text-sm text-gray-600">{{ $settings['description'] }}</p>
                        </div>
                        <span class="report-condition-badge report-condition-badge--{{ $type }}">
                            {{ $settings['example'] }}
                        </span>
                    </div>

                    <div class="mt-4 grid grid-cols-3 gap-3">
                        @foreach ($colorRoles as $role => $label)
                            <label class="min-w-0 text-xs font-medium text-gray-700" for="report-tag-{{ $type }}-{{ $role }}">
                                <span class="block truncate">{{ $label }}</span>
                                <input
                                    id="report-tag-{{ $type }}-{{ $role }}"
                                    type="color"
                                    class="mt-1 h-11 w-full cursor-pointer rounded-md border border-gray-300 bg-white p-1"
                                    x-bind:value="palette.{{ $type }}.{{ $role }}"
                                    x-on:input="palette.{{ $type }}.{{ $role }} = $event.target.value; preview()"
                                    title="{{ __('ui.home.tag_palette.choose_color', ['name' => $label]) }}"
                                >
                                <output class="mt-1 block font-mono text-[11px] font-normal text-gray-500" x-text="palette.{{ $type }}.{{ $role }}"></output>
                            </label>
                        @endforeach
                    </div>
                </section>
            @endforeach

            <div class="grid gap-2 sm:grid-cols-3" role="group" aria-label="{{ __('ui.home.tag_palette.tools') }}">
                <button
                    type="button"
                    x-on:click="reset()"
                    class="inline-flex items-center justify-center gap-2 rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-xs hover:bg-gray-50 focus:outline-hidden focus:ring-2 focus:ring-blue-600 focus:ring-offset-2"
                >
                    <svg class="size-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M15.312 11.424a5.5 5.5 0 0 1-9.201 2.206.75.75 0 0 0-1.06 1.06 7 7 0 1 0-.687-8.562l-.43-2.148a.75.75 0 0 0-1.47.294l.75 3.75a.75.75 0 0 0 .882.588l3.75-.75a.75.75 0 0 0-.294-1.47l-1.621.324a5.5 5.5 0 1 1 9.381 4.708Z" clip-rule="evenodd" />
                    </svg>
                    {{ __('ui.home.tag_palette.reset') }}
                </button>
                <button
                    type="button"
                    x-on:click="copy()"
                    class="inline-flex items-center justify-center gap-2 rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-xs hover:bg-gray-50 focus:outline-hidden focus:ring-2 focus:ring-blue-600 focus:ring-offset-2"
                >
                    <svg class="size-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M5.5 2A1.5 1.5 0 0 0 4 3.5v9A1.5 1.5 0 0 0 5.5 14H7v-1.5H5.5v-9h7V5H14V3.5A1.5 1.5 0 0 0 12.5 2h-7Z" />
                        <path d="M8 7.5A1.5 1.5 0 0 1 9.5 6h5A1.5 1.5 0 0 1 16 7.5v9a1.5 1.5 0 0 1-1.5 1.5h-5A1.5 1.5 0 0 1 8 16.5v-9Zm1.5 0v9h5v-9h-5Z" />
                    </svg>
                    <span x-show="! copied">{{ __('ui.home.tag_palette.copy') }}</span>
                    <span x-show="copied" x-cloak>{{ __('ui.home.tag_palette.copied') }}</span>
                </button>
                <button
                    type="button"
                    x-on:click="toggleImport()"
                    x-bind:aria-expanded="importOpen"
                    aria-controls="report-tag-palette-import"
                    class="inline-flex items-center justify-center gap-2 rounded-md border px-3 py-2 text-sm font-medium shadow-xs focus:outline-hidden focus:ring-2 focus:ring-blue-600 focus:ring-offset-2"
                    x-bind:class="importOpen
                        ? 'border-blue-300 bg-blue-50 text-blue-700'
                        : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50'"
                >
                    <svg class="size-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M10.75 2.75a.75.75 0 0 0-1.5 0v8.69L6.03 8.22a.75.75 0 0 0-1.06 1.06l4.5 4.5a.75.75 0 0 0 1.06 0l4.5-4.5a.75.75 0 1 0-1.06-1.06l-3.22 3.22V2.75Z" />
                        <path d="M3.5 12.5a.75.75 0 0 0-1.5 0v2.75A2.75 2.75 0 0 0 4.75 18h10.5A2.75 2.75 0 0 0 18 15.25V12.5a.75.75 0 0 0-1.5 0v2.75c0 .69-.56 1.25-1.25 1.25H4.75c-.69 0-1.25-.56-1.25-1.25V12.5Z" />
                    </svg>
                    {{ __('ui.home.tag_palette.import') }}
                </button>
            </div>

            <section
                id="report-tag-palette-import"
                x-show="importOpen"
                x-cloak
                class="rounded-lg border border-blue-200 bg-blue-50 p-4"
            >
                <label for="report-tag-palette-json" class="block text-sm font-semibold text-gray-900">
                    {{ __('ui.home.tag_palette.import_title') }}
                </label>
                <p id="report-tag-palette-import-help" class="mt-1 text-sm text-gray-600">
                    {{ __('ui.home.tag_palette.import_description') }}
                </p>
                <textarea
                    id="report-tag-palette-json"
                    x-ref="importInput"
                    x-model="importValue"
                    x-on:input="importError = false; imported = false"
                    rows="4"
                    class="mt-3 block w-full rounded-md border-gray-300 bg-white font-mono text-xs shadow-xs focus:border-blue-600 focus:ring-blue-600"
                    placeholder="{{ __('ui.home.tag_palette.import_placeholder') }}"
                    aria-describedby="report-tag-palette-import-help"
                ></textarea>
                <p x-show="importError" x-cloak class="mt-2 text-sm font-medium text-red-700" role="alert">
                    {{ __('ui.home.tag_palette.import_error') }}
                </p>
                <p x-show="imported" x-cloak class="mt-2 text-sm font-medium text-green-700" role="status">
                    {{ __('ui.home.tag_palette.import_success') }}
                </p>
                <div class="mt-3 flex justify-end">
                    <button
                        type="button"
                        x-on:click="importPalette()"
                        x-bind:disabled="! importValue.trim()"
                        class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {{ __('ui.home.tag_palette.import_apply') }}
                    </button>
                </div>
            </section>
        </div>

        <div class="grid shrink-0 grid-cols-2 gap-3 border-t border-gray-200 bg-gray-50 p-4 sm:flex sm:justify-end sm:px-6">
            <button type="button" x-on:click="cancel()" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-xs hover:bg-gray-50 focus:outline-hidden focus:ring-2 focus:ring-blue-600 focus:ring-offset-2 sm:min-w-28">
                {{ __('ui.common.cancel') }}
            </button>
            <button type="button" x-on:click="save()" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-xs hover:bg-blue-700 focus:outline-hidden focus:ring-2 focus:ring-blue-600 focus:ring-offset-2 sm:min-w-28">
                {{ __('ui.home.tag_palette.save') }}
            </button>
        </div>
    </div>
</div>
