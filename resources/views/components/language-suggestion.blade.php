@if ($suggestedLocale = request()->attributes->get('suggested_locale'))
    <div
        x-data="{ visible: true }"
        x-show="visible"
        class="fixed inset-x-3 top-3 z-50 mx-auto flex max-w-md items-center justify-between gap-3 rounded-lg border border-blue-200 bg-white p-3 text-sm text-gray-800 shadow-lg"
        role="status"
    >
        <span>{{ __('locale.suggestion') }}</span>
        <div class="flex shrink-0 items-center gap-2">
            <form method="POST" action="{{ route('locale.update', ['locale' => $suggestedLocale]) }}">
                @csrf
                <input type="hidden" name="redirect" value="{{ localized_path($suggestedLocale) }}">
                <button type="submit" class="font-semibold text-blue-700 hover:text-blue-900 hover:underline">
                    {{ __('locale.open_suggestion') }}
                </button>
            </form>
            <form method="POST" action="{{ route('locale.update', ['locale' => config('localization.default')]) }}">
                @csrf
                <input type="hidden" name="redirect" value="{{ localized_path(config('localization.default')) }}">
                <button
                    type="submit"
                    class="rounded p-1 text-gray-500 hover:bg-stone-100 hover:text-gray-900"
                    aria-label="{{ __('locale.dismiss') }}"
                    @click="visible = false"
                >
                    ×
                </button>
            </form>
        </div>
    </div>
@endif
