<div class="h-full" x-data="mapReports" @map-viewport-changed.window.debounce.250ms="refresh()"
    @map-filters-changed.window="refresh()" @map-track-changed.window="refresh()"
    @scroll.window="busy && positionLoader()" @resize.window="busy && positionLoader()">
    <div>
        @if ($userId)
            <div class="px-4 flex items-stretch">
                <div
                class="rounded-lg flex items-center">
                    <div class="mr-2 text-xl font-medium">{{ $user?->name }}</div>
                    <span class="ml-0 text-sm font-medium px-1.5 py-0 rounded-full bg-[#FFD300]/25 border border-[#ff6633]">{{ $user?->rating }}</span>
                </div>
            </div>
        @else
            <div class="px-4">
                <span class="font-normal text-base text-blue-600 hover:text-blue-700">
                    <span class="text-gray-900 mr-1">{{ __('ui.home.tagline') }}</span>
                
                <a href="{{ url((app()->getLocale() === config('localization.default') ? '' : '/' . app()->getLocale()) . '/docs/about') }}" class="text-blue-600 font-normal text-base text-blue-600 hover:text-blue-700 whitespace-nowrap">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="relative -mt-0.5 inline" viewBox="0 0 16 16">
                        <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/>
                    </svg>
                    <span class="hover:underline">
                        {{ __('ui.common.about') }}
                    </span>
                </a>
                {{-- 
                    <div class="">
                        <a href="https://www.instagram.com/rodnik.today/" target="_blank" class="flex items-center font-normal text-sm text-blue-600 hover:text-blue-700">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="mr-1 block" viewBox="0 0 16 16">
                                <path d="M8 0C5.829 0 5.556.01 4.703.048 3.85.088 3.269.222 2.76.42a3.917 3.917 0 0 0-1.417.923A3.927 3.927 0 0 0 .42 2.76C.222 3.268.087 3.85.048 4.7.01 5.555 0 5.827 0 8.001c0 2.172.01 2.444.048 3.297.04.852.174 1.433.372 1.942.205.526.478.972.923 1.417.444.445.89.719 1.416.923.51.198 1.09.333 1.942.372C5.555 15.99 5.827 16 8 16s2.444-.01 3.298-.048c.851-.04 1.434-.174 1.943-.372a3.916 3.916 0 0 0 1.416-.923c.445-.445.718-.891.923-1.417.197-.509.332-1.09.372-1.942C15.99 10.445 16 10.173 16 8s-.01-2.445-.048-3.299c-.04-.851-.175-1.433-.372-1.941a3.926 3.926 0 0 0-.923-1.417A3.911 3.911 0 0 0 13.24.42c-.51-.198-1.092-.333-1.943-.372C10.443.01 10.172 0 7.998 0h.003zm-.717 1.442h.718c2.136 0 2.389.007 3.232.046.78.035 1.204.166 1.486.275.373.145.64.319.92.599.28.28.453.546.598.92.11.281.24.705.275 1.485.039.843.047 1.096.047 3.231s-.008 2.389-.047 3.232c-.035.78-.166 1.203-.275 1.485a2.47 2.47 0 0 1-.599.919c-.28.28-.546.453-.92.598-.28.11-.704.24-1.485.276-.843.038-1.096.047-3.232.047s-2.39-.009-3.233-.047c-.78-.036-1.203-.166-1.485-.276a2.478 2.478 0 0 1-.92-.598 2.48 2.48 0 0 1-.6-.92c-.109-.281-.24-.705-.275-1.485-.038-.843-.046-1.096-.046-3.233 0-2.136.008-2.388.046-3.231.036-.78.166-1.204.276-1.486.145-.373.319-.64.599-.92.28-.28.546-.453.92-.598.282-.11.705-.24 1.485-.276.738-.034 1.024-.044 2.515-.045v.002zm4.988 1.328a.96.96 0 1 0 0 1.92.96.96 0 0 0 0-1.92zm-4.27 1.122a4.109 4.109 0 1 0 0 8.217 4.109 4.109 0 0 0 0-8.217zm0 1.441a2.667 2.667 0 1 1 0 5.334 2.667 2.667 0 0 1 0-5.334z"/>
                            </svg>
                            <div>
                                Instagram
                            </div>
                        </a>
                    </div>
                --}}
            </div>
            <div class="mt-2 px-4 mb-3 text-sm font-medium">
                {!! trans_choice('ui.home.water_sources_count', $springsCount, ['count' => '<span class="px-1.5 py-0 rounded-full bg-[#33A9FF]/10 border border-[#33A9FF]">' . number_format($springsCount, 0, ',', ' ') . '</span>']) !!}
                {!! trans_choice('ui.home.with_reports_count', $reportsCount, ['count' => '<span class="ml-0 px-1.5 py-0 rounded-full bg-[#FFD300]/25 border border-[#ff6633]">' . number_format($reportsCount, 0, ',', ' ') . '</span>']) !!}.
            </div>
        @endif
    </div>
    <section :aria-busy="busy">
        @if (! $userId)
            <div class="px-4 mt-4 mb-2">
                <h2 class="font-semibold">{{ __('ui.home.reports_in_area') }}</h2>
                <div x-cloak x-show="loaderTop !== null && (busy || (! $wire.bounds && ! failed))" role="status"
                    class="pointer-events-none fixed inset-x-0 z-20 flex items-center justify-center overflow-hidden bg-stone-100/80 sm:bottom-0 sm:left-1/2"
                    :style="{ top: loaderTop + 'px' }"
                    :class="{ 'bottom-10': minimized, 'bottom-[50vh]': ! minimized }">
                    <div aria-hidden="true" class="animate-spin w-6 h-6 border-4 rounded-full border-stone-400 border-t-transparent"></div>
                    <span class="sr-only">{{ __('ui.home.loading_reports') }}</span>
                </div>
                <button x-cloak x-show="failed" @click="refresh(retryMore)" type="button" class="mt-1 text-sm text-blue-600 hover:underline">{{ __('ui.home.retry_reports') }}</button>
            </div>
        @endif
        <ul x-ref="reportsList" x-cloak role="list" class="grid grid-cols-2 lg:grid-cols-3 mt-2 md:px-4
            bg-stone-200
            border-t
            border-b
            border-stone-200
            gap-px
            md:bg-inherit
            md:border-0
            md:gap-4 items-stretch md:items-start" wire:key="reports" :class="{ 'opacity-50': busy || failed }" :inert="busy || failed">
            @foreach ($lastReports as $report)
                <x-last-reports.teaser :report="$report" :preserve-map-view="! $userId" />
            @endforeach
        </ul>
        @if (! $userId && $bounds && $lastReports->isEmpty())
            <p x-show="! busy && ! failed" role="status" class="px-4 py-8 text-sm text-gray-600">{{ __('ui.home.no_reports_in_area') }}</p>
        @endif
        @if (! $userId && $hasMore)
            <div class="px-4 pb-6">
                <button @click="refresh(true)" :disabled="busy || failed" type="button" class="w-full p-3 bg-stone-200 rounded-xl mt-4 text-sm disabled:opacity-50">{{ __('ui.home.show_more_area_reports') }}</button>
            </div>
        @elseif ($userId && count($lastReports) == $limit)
            <livewire:duo.components.show-more-reports
                user-id="{{ $userId }}"
                skip="{{ $limit }}"
                take="{{ $limit }}"
                key="show-more-reports-user-{{ $userId }}-skip-{{ $limit }}-take-{{ $limit }}"
                />
        @endif
    </section>
</div>
