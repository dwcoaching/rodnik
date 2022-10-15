<div id="spring"
    x-data="{
        locateMap: true,
        springsSource: function(userId = 0) {
            window.rodnikMap.springsSource(userId);
            this.userId = userId;
        },
        userId: @entangle('userId'),
    }"
    x-on:spring-selected.window="
        $wire.setSpring($event.detail.id);
        ym(90143259, 'hit', window.location.origin + '/' + $event.detail.id);
    "
    x-on:spring-unselected.window="
        $wire.unselectSpring();
        ym(90143259, 'hit', window.location.origin + '/');
    "
    x-on:popstate.window="
        if ($event.state && $event.state.springId) {
            locateMap = true;
            const event = new CustomEvent('spring-selected', {detail: {id: $event.state.springId}});
            window.dispatchEvent(event);
        } else if ($event.state && $event.state.userId) {
            springsSource($event.state.userId);
            window.rodnikMap.unselectPreviousFeature();
            const event = new CustomEvent('spring-unselected');
            window.dispatchEvent(event);
        } else {
            springsSource(0);
            const event = new CustomEvent('spring-unselected');
            window.dispatchEvent(event);
        }"
    x-init="
        if ({{ intval($springId)}} && locateMap) {
            window.rodnikMap.locate({{ json_encode($coordinates) }});
            window.rodnikMap.showFeature({{ $springId }});
        }

        if ({{ intval($user instanceof \App\Models\User) }}) {
            window.rodnikMap.locateWorld();
        }

        locateMap = false;

        window.initPhotoSwipe('#spring');
    ">
    @if (! $spring)
        <div>
            {{--
                <div>
                    @if (Auth::check() && Auth::user()->springs->count() > 0)
                        <div class="mb-4">
                            <button @click="springsSource()" type="button" class="mr-1 inline-flex items-center px-4 py-2 border-2 border-blue-600 text-sm font-medium rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                :class="{
                                    'bg-white': userId != 0,
                                    'bg-blue-600': userId == 0,
                                    'text-blue-700': userId != 0,
                                    'text-white': userId == 0
                                }"
                            >Все родники</button>
                            <button @click="springsSource({{ Auth::user()->id }})" type="button" class="mr-1 inline-flex items-center px-4 py-2 border-2 border-blue-600 text-sm font-medium rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                :class="{
                                    'bg-white': userId == 0,
                                    'bg-blue-600': userId != 0,
                                    'text-blue-700': userId == 0,
                                    'text-white': userId != 0
                                }"
                            >Мои родники</button>
                        </div>
                    @endif
                </div>
            --}}

        @if (! $user)
            <div class="text-sm">
                <a href="https://t.me/rodnik_today" target="_blank" class="font-medium text-blue-600 hover:text-blue-700">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="inline" viewBox="0 0 16 16">
                                <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zM8.287 5.906c-.778.324-2.334.994-4.666 2.01-.378.15-.577.298-.595.442-.03.243.275.339.69.47l.175.055c.408.133.958.288 1.243.294.26.006.549-.1.868-.32 2.179-1.471 3.304-2.214 3.374-2.23.05-.012.12-.026.166.016.047.041.042.12.037.141-.03.129-1.227 1.241-1.846 1.817-.193.18-.33.307-.358.336a8.154 8.154 0 0 1-.188.186c-.38.366-.664.64.015 1.088.327.216.589.393.85.571.284.194.568.387.936.629.093.06.183.125.27.187.331.236.63.448.997.414.214-.02.435-.22.547-.82.265-1.417.786-4.486.906-5.751a1.426 1.426 0 0 0-.013-.315.337.337 0 0 0-.114-.217.526.526 0 0 0-.31-.093c-.3.005-.763.166-2.984 1.09z"/>
                            </svg> группа для общения
                </a>
                &nbsp;и&nbsp;
                <a href="https://t.me/rodniktoday" target="_blank" class="font-medium text-blue-600 hover:text-blue-700">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="inline" viewBox="0 0 16 16">
                                <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zM8.287 5.906c-.778.324-2.334.994-4.666 2.01-.378.15-.577.298-.595.442-.03.243.275.339.69.47l.175.055c.408.133.958.288 1.243.294.26.006.549-.1.868-.32 2.179-1.471 3.304-2.214 3.374-2.23.05-.012.12-.026.166.016.047.041.042.12.037.141-.03.129-1.227 1.241-1.846 1.817-.193.18-.33.307-.358.336a8.154 8.154 0 0 1-.188.186c-.38.366-.664.64.015 1.088.327.216.589.393.85.571.284.194.568.387.936.629.093.06.183.125.27.187.331.236.63.448.997.414.214-.02.435-.22.547-.82.265-1.417.786-4.486.906-5.751a1.426 1.426 0 0 0-.013-.315.337.337 0 0 0-.114-.217.526.526 0 0 0-.31-.093c-.3.005-.763.166-2.984 1.09z"/>
                            </svg> канал с уведомлениями
                </a> в телеграме
            </div>
        @endif

            <div>
                @if ($user)
                    <div class="mt-2 text-2xl font-bold flex items-center">
                        <div class="mr-1">{{ $user->name }}</div>
                        <div class="-mt-2 text-sm font-semibold text-gray-900">{{ $user->rating }}</div>
                    </div>
                @else
                    <div class="mt-6 text-2xl font-bold">Последние отчеты</div>
                @endif

                <ul role="list" class="pt-2" wire:key="reports">
                    @foreach ($lastReports as $report)
                        <livewire:reports.show has-name="true" :report="$report" wire:key="reports.latest.show.{{ $report->id }}" />
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    @if ($spring)
        <div class="" wire:key="spring.{{ $spring->id }}">
            <div class="flex items-center flex-wrap">
                <span class="text-3xl font-bold mr-3">
                    {{ $spring->name ? $spring->name : $spring->type }}
                </span>
                <div class="flex items-center">
                    <span class="mr-3 text-gray-600 text-2xl font-thin">#{{ $spring->id }}</span>
                    @can('update', $spring)
                        <a href="{{ route('springs.edit', $spring) }}"
                            class="inline-block text-xs font-regular text-blue-600 cursor-pointer rounded-full bg-white border shadow-sm  px-2.5 py-1 border-white">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
                                <path d="M5.433 13.917l1.262-3.155A4 4 0 017.58 9.42l6.92-6.918a2.121 2.121 0 013 3l-6.92 6.918c-.383.383-.84.685-1.343.886l-3.154 1.262a.5.5 0 01-.65-.65z" />
                                <path d="M3.5 5.75c0-.69.56-1.25 1.25-1.25H10A.75.75 0 0010 3H4.75A2.75 2.75 0 002 5.75v9.5A2.75 2.75 0 004.75 18h9.5A2.75 2.75 0 0017 15.25V10a.75.75 0 00-1.5 0v5.25c0 .69-.56 1.25-1.25 1.25h-9.5c-.69 0-1.25-.56-1.25-1.25v-9.5z" />
                            </svg>
                        </a>
                    @endcan
                </div>
            </div>
            <div class="text-gray-600 mt-2 text-sm flex flex-wrap items-center">
                @if ($spring->name)
                    <div class="text-sm mr-3 mb-2">
                        {{ $spring->type }}
                    </div>
                @endif
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="mb-2 mr-1 block w-5 h-5">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-1.5 0a6.5 6.5 0 11-11-4.69v.447a3.5 3.5 0 001.025 2.475L8.293 10 8 10.293a1 1 0 000 1.414l1.06 1.06a1.5 1.5 0 01.44 1.061v.363a1 1 0 00.553.894l.276.139a1 1 0 001.342-.448l1.454-2.908a1.5 1.5 0 00-.281-1.731l-.772-.772a1 1 0 00-1.023-.242l-.384.128a.5.5 0 01-.606-.25l-.296-.592a.481.481 0 01.646-.646l.262.131a1 1 0 00.447.106h.188a1 1 0 00.949-1.316l-.068-.204a.5.5 0 01.149-.538l1.44-1.234A6.492 6.492 0 0116.5 10z" clip-rule="evenodd" />
                </svg>
                <span class="mr-3 mb-2">
                    <div class="text-gray-600 text-sm cursor-pointer"
                        x-data="{
                            copied: false,
                            timeout: null
                        }"
                        @click="
                            $clipboard('{{ $spring->latitude }}, {{ $spring->longitude }}');
                            copied = true;
                            clearTimeout(timeout);
                            timeout = setTimeout(() => {
                                copied = false;
                            }, 1000)
                        "
                        >
                            <span class="">{{ $spring->latitude }}, {{ $spring->longitude }}</span>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="inline -mt-0.5 w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 7.5V6.108c0-1.135.845-2.098 1.976-2.192.373-.03.748-.057 1.123-.08M15.75 18H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08M15.75 18.75v-1.875a3.375 3.375 0 00-3.375-3.375h-1.5a1.125 1.125 0 01-1.125-1.125v-1.5A3.375 3.375 0 006.375 7.5H5.25m11.9-3.664A2.251 2.251 0 0015 2.25h-1.5a2.251 2.251 0 00-2.15 1.586m5.8 0c.065.21.1.433.1.664v.75h-6V4.5c0-.231.035-.454.1-.664M6.75 7.5H4.875c-.621 0-1.125.504-1.125 1.125v12c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V16.5a9 9 0 00-9-9z" />
                            </svg>


                            <span x-cloak x-show="copied" x-transition.opacity.duration.300 class="font-regular">
                                cкопировано
                            </span>
                        </div>
                </span>
            </div>
        </div>

        @if ($spring->osm_tags->count())
            <div class="mt-3">
                <div class="text-gray-900 text-sm">
                    <span class="font-semibold mr-3">
                        Теги OSM
                    </span>
                    @if ($spring->osm_node_id)
                        <span class="text-xs text-gray-500">
                            (node id: {{ $spring->osm_node_id }})
                        </span>
                    @endif
                    @if ($spring->osm_way_id)
                        <span class="text-xs text-gray-500">
                            (way id: {{ $spring->osm_way_id }})
                        </span>
                    @endif
                </div>
                @foreach ($spring->osm_tags as $tag)
                    <div class="text-gray-900 mt-1 text-sm">{{ $tag->key }}: {{ $tag->value }}</div>
                @endforeach
            </div>
        @endif

        @if ($reports->count())
            <div class="flex mt-16 items-center justify-between">
                <div class="text-3xl font-bold">Отчеты</div>
                <a href="{{ route('reports.create', ['spring_id' => $spring]) }}" type="button" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-full text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <svg xmlns="http://www.w3.org/2000/svg" class="-ml-1 mr-2 h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v2H7a1 1 0 100 2h2v2a1 1 0 102 0v-2h2a1 1 0 100-2h-2V7z" clip-rule="evenodd" />
                    </svg>
                    Добавить&nbsp;отчет
                </a>
            </div>
            <div class="mt-3">
                <ul role="list" class="">
                    @foreach ($reports as $report)
                        <livewire:reports.show has-name="false" :report="$report" wire:key="spring.reports.show.{{ $report->id }}" />
                    @endforeach
                </ul>
            </div>
        @else
            <div class="mt-16">
                <a href="{{ route('reports.create', ['spring_id' => $spring]) }}" type="button" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-full text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <svg xmlns="http://www.w3.org/2000/svg" class="-ml-1 mr-2 h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v2H7a1 1 0 100 2h2v2a1 1 0 102 0v-2h2a1 1 0 100-2h-2V7z" clip-rule="evenodd" />
                    </svg>
                    Добавить&nbsp;отчет
                </a>
            </div>
        @endif
    @endif
</div>
