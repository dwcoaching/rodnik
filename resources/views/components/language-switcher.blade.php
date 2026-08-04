@props(['align' => 'right'])

@php
    $supportedLocales = config('localization.supported');
    $currentLocale = $supportedLocales[app()->getLocale()] ?? reset($supportedLocales);
@endphp

<div
    x-data="{ languageMenuOpen: false }"
    @keydown.escape.window="languageMenuOpen = false"
    @click.outside="languageMenuOpen = false"
    {{ $attributes->class(['relative']) }}
>
    <button
        type="button"
        @click="languageMenuOpen = ! languageMenuOpen"
        :aria-expanded="languageMenuOpen ? 'true' : 'false'"
        aria-haspopup="listbox"
        aria-label="{{ __('locale.language') }}"
        class="flex items-center gap-1 rounded-md px-2 py-1 text-xs font-semibold text-gray-600 transition-colors hover:bg-stone-200 hover:text-gray-950 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-700"
    >
        <span>{{ $currentLocale['short_name'] }}</span>
        <svg
            class="h-3 w-3 shrink-0 transition-transform"
            :class="languageMenuOpen && 'rotate-180'"
            viewBox="0 0 20 20"
            fill="currentColor"
            aria-hidden="true"
        >
            <path fill-rule="evenodd" d="M5.22 7.22a.75.75 0 0 1 1.06 0L10 10.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 8.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
        </svg>
    </button>

    <div
        x-cloak
        x-show="languageMenuOpen"
        x-transition.opacity.duration.100ms
        role="listbox"
        class="absolute top-full z-30 mt-1 w-40 overflow-hidden rounded-lg border border-stone-300 bg-white p-1 shadow-lg
            {{ $align === 'left' ? 'left-0 origin-top-left' : 'right-0 origin-top-right' }}"
    >
        @foreach ($supportedLocales as $locale => $details)
            <x-locale-form :locale="$locale">
                <button
                    type="submit"
                    role="option"
                    aria-selected="{{ app()->isLocale($locale) ? 'true' : 'false' }}"
                    title="{{ __('locale.switch_to', ['language' => $details['name']]) }}"
                    class="flex w-full items-center justify-between gap-2 rounded-md px-3 py-2 text-left text-sm font-medium transition-colors hover:bg-stone-200 hover:text-gray-950
                        {{ app()->isLocale($locale) ? 'bg-stone-100 text-gray-900' : 'text-gray-600' }}"
                >
                    <span>{{ $details['name'] }}</span>
                    @if (app()->isLocale($locale))
                        <svg class="h-4 w-4 shrink-0 text-blue-700" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M16.704 5.29a.75.75 0 0 1 .006 1.06l-7.5 7.6a.75.75 0 0 1-1.07-.004l-3.85-3.9a.75.75 0 1 1 1.068-1.054l3.317 3.36 6.968-7.062a.75.75 0 0 1 1.06-.006Z" clip-rule="evenodd" />
                        </svg>
                    @endif
                </button>
            </x-locale-form>
        @endforeach
    </div>
</div>
