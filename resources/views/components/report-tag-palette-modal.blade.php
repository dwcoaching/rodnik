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
        class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-lg bg-white shadow-xl"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
    >
        <div class="flex items-start justify-between gap-4 border-b border-gray-200 p-5 sm:p-6">
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

        <div class="space-y-4 p-5 sm:p-6">
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
        </div>

        <div class="flex flex-col-reverse gap-3 border-t border-gray-200 p-5 sm:flex-row sm:flex-wrap sm:items-center sm:p-6">
            <button type="button" x-on:click="reset()" class="rounded-md px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">
                {{ __('ui.home.tag_palette.reset') }}
            </button>
            <button type="button" x-on:click="copy()" class="rounded-md px-3 py-2 text-sm font-medium text-blue-700 hover:bg-blue-50">
                <span x-show="! copied">{{ __('ui.home.tag_palette.copy') }}</span>
                <span x-show="copied" x-cloak>{{ __('ui.home.tag_palette.copied') }}</span>
            </button>
            <div class="hidden grow sm:block"></div>
            <button type="button" x-on:click="cancel()" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                {{ __('ui.common.cancel') }}
            </button>
            <button type="button" x-on:click="save()" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                {{ __('ui.home.tag_palette.save') }}
            </button>
        </div>
    </div>
</div>
