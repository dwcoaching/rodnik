<div {{ $attributes->class(['flex items-center gap-1']) }} aria-label="{{ __('locale.language') }}">
    @foreach (config('localization.supported') as $locale => $details)
        @continue(app()->isLocale($locale))

        <form method="POST" action="{{ route('locale.update', ['locale' => $locale]) }}">
            @csrf
            <input type="hidden" name="redirect" value="{{ localized_path($locale) }}">
            <button
                type="submit"
                class="rounded-md px-2 py-1 text-xs font-semibold text-gray-600 transition-colors hover:bg-stone-200 hover:text-gray-950 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-700"
                title="{{ __('locale.switch_to', ['language' => $details['name']]) }}"
            >
                {{ $details['short_name'] }}
            </button>
        </form>
    @endforeach
</div>
