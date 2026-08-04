@if ($suggestedLocale = request()->attributes->get('suggested_locale'))
    <div
        x-data="{ visible: true }"
        x-show="visible"
        class="fixed inset-x-3 top-3 z-50 mx-auto flex max-w-md items-center justify-between gap-3 rounded-lg border border-blue-200 bg-white p-3 text-sm text-gray-800 shadow-lg"
        role="status"
    >
        <span>{{ __('locale.suggestion') }}</span>
        <div class="flex shrink-0 items-center gap-2">
            <x-locale-form :locale="$suggestedLocale">
                <button type="submit" class="font-semibold text-blue-700 hover:text-blue-900 hover:underline">
                    {{ __('locale.open_suggestion') }}
                </button>
            </x-locale-form>
            <x-locale-form :locale="config('localization.default')">
                <button
                    type="submit"
                    class="rounded p-1 text-gray-500 hover:bg-stone-100 hover:text-gray-900"
                    aria-label="{{ __('locale.dismiss') }}"
                    @click="visible = false"
                >
                    ×
                </button>
            </x-locale-form>
        </div>
    </div>
@endif
